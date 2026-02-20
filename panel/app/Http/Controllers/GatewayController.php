<?php

namespace App\Http\Controllers;

use App\Models\DeviceAntiSpamSetting;
use App\Models\DeviceOwnership;
use App\Models\User;
use App\Services\GatewayService;
use App\Services\Pm2Service;
use App\Services\SessionConfigStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class GatewayController extends Controller
{
    public function index(Request $request): View
    {
        $gateway = $this->gateway();
        $sessions = [];
        $health = null;
        $apiError = null;
        $sessionConfigs = [];
        $sessionStatuses = [];
        $user = $request->user();

        try {
            $sessions = $gateway->listSessions();
            $health = $gateway->health();
            $sessionStatuses = $gateway->listSessionStatuses();
        } catch (Throwable $e) {
            $apiError = $e->getMessage();
        }

        $sessions = $this->filterSessionsForUser($sessions, $user);
        $allowedSessions = array_flip($sessions);
        $sessionStatuses = array_values(array_filter($sessionStatuses, function ($row) use ($allowedSessions) {
            $id = is_array($row) ? ($row['id'] ?? null) : null;
            return $id && isset($allowedSessions[$id]);
        }));

        $pm2   = $this->pm2();
        $store = $this->sessionConfigStore();
        foreach ($sessions as $session) {
            $sessionConfigs[$session] = $store->get($session);
        }

        $serverStatus = $pm2->status();

        // Jika API aktif tapi PM2 tidak mendeteksi proses (mis. dijalankan manual),
        // tandai sebagai running agar UI tidak memperlihatkan tombol Start secara salah.
        if (!$serverStatus['running'] && $health) {
            $serverStatus['running']   = true;
            $serverStatus['pm2Status'] = 'online (external)';
            $serverStatus['pid']       = null;
        }

        $logTail = null;
        $logFile = $serverStatus['logFile'] ?? null;
        if (is_string($logFile) && $logFile !== '' && is_readable($logFile) && filesize($logFile) > 0) {
            try {
                $lines   = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                $logTail = implode("\n", array_slice($lines, -30));
            } catch (Throwable) {
                $logTail = null;
            }
        }

        return view('dashboard', [
            'sessions'        => $sessions,
            'health'          => $health,
            'apiError'        => $apiError,
            'sessionStatuses' => $sessionStatuses,
            'qrData'          => session('qr'),
            'qrSession'       => session('qrSession'),
            'pairingCode'     => session('pairingCode'),
            'pairingSession'  => session('pairingSession'),
            'statusMessage'   => session('status'),
            'autoRefresh'     => session('autoRefresh'),
            'errorsBag'       => $request->session()->get('errors'),
            'serverStatus'    => $serverStatus,
            'sessionConfigs'  => $sessionConfigs,
            'isAdmin'         => $this->isAdminUser($user),
            'gatewayConfig'   => [
                'base' => config('gateway.base_url'),
                'key'  => config('gateway.api_key'),
            ],
            'logTail'             => $logTail,
            'permissionWarnings'  => $this->permissionWarnings(),
        ]);
    }

    public function deviceManagement(Request $request): View
    {
        $gateway = $this->gateway();
        $sessions = [];
        $health = null;
        $apiError = null;
        $sessionConfigs = [];
        $sessionStatuses = [];
        $user = $request->user();

        try {
            $sessions = $gateway->listSessions();
            $health = $gateway->health();
            $sessionStatuses = $gateway->listSessionStatuses();
        } catch (Throwable $e) {
            $apiError = $e->getMessage();
        }

        $sessions = $this->filterSessionsForUser($sessions, $user);
        $allowedSessions = array_flip($sessions);
        $sessionStatuses = array_values(array_filter($sessionStatuses, function ($row) use ($allowedSessions) {
            $id = is_array($row) ? ($row['id'] ?? null) : null;
            return $id && isset($allowedSessions[$id]);
        }));

        $store = $this->sessionConfigStore();
        $antiSpamSettings = [];
        foreach ($sessions as $session) {
            $sessionConfigs[$session] = $store->get($session);
            $antiSpamSettings[$session] = DeviceAntiSpamSetting::getForSession($session);
        }

        $ownerships = [];
        $users = [];
        if ($this->isAdminUser($user)) {
            $users = User::orderBy('name')->get(['id', 'name', 'email', 'role']);
            $ownerships = DeviceOwnership::with('user:id,name,email,role')
                ->whereIn('session_id', $sessions)
                ->get()
                ->mapWithKeys(function (DeviceOwnership $ownership) {
                    $owner = $ownership->user;
                    return [
                        $ownership->session_id => $owner
                            ? [
                                'id' => $owner->id,
                                'name' => $owner->name,
                                'email' => $owner->email,
                                'role' => $owner->role,
                            ]
                            : null,
                    ];
                })
                ->all();
        }

        return view('devices', [
            'sessions' => $sessions,
            'health' => $health,
            'apiError' => $apiError,
            'sessionStatuses' => $sessionStatuses,
            'qrData' => session('qr'),
            'qrSession' => session('qrSession'),
            'pairingCode' => session('pairingCode'),
            'pairingSession' => session('pairingSession'),
            'statusMessage' => session('status'),
            'errorsBag' => $request->session()->get('errors'),
            'sessionConfigs' => $sessionConfigs,
            'antiSpamSettings' => $antiSpamSettings,
            'isAdmin' => $this->isAdminUser($user),
            'gatewayConfig' => [
                'base' => config('gateway.base_url'),
                'key' => config('gateway.api_key'),
            ],
            'resetSessions' => config('gateway.password_reset_sessions', []),
            'permissionWarnings' => $this->permissionWarnings(),
            'ownerships' => $ownerships,
            'users' => $users,
        ]);
    }

    public function createDevice(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'device_name' => ['required', 'string'],
            'device_phone' => ['required', 'string'],
            'mode' => ['nullable', 'string'],
        ]);

        $sessionId = preg_replace('/\s+/', '', trim($data['device_phone'] ?? ''));
        if (!$sessionId) {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['device' => 'Nomor WA device tidak valid.']);
        }

        $store = $this->sessionConfigStore();
        $existingConfig = $store->get($sessionId);
        $hasConfig = is_array($existingConfig) && count($existingConfig) > 0;

        if ($hasConfig) {
            $store->delete($sessionId);
            try {
                $this->gateway()->deleteSession($sessionId);
            } catch (Throwable) {
                // ignore cleanup failure
            }
        }

        // Pastikan session lama dibersihkan dulu sebelum membuat ulang.
        try {
            $this->gateway()->deleteSession($sessionId);
        } catch (Throwable) {
            // ignore
        }

        // Pastikan session lama dibersihkan dulu sebelum membuat ulang.
        try {
            $this->gateway()->deleteSession($sessionId);
        } catch (Throwable) {
            // abaikan jika tidak ada
        }

        // Jika device pernah dihapus dari panel tapi masih ada sisa session di gateway, bersihkan agar bisa dibuat ulang.
        try {
            $sessions = $this->gateway()->listSessions();
            if (in_array($sessionId, $sessions, true)) {
                $this->gateway()->deleteSession($sessionId);
            }
        } catch (Throwable) {
            // ignore
        }

        $store->put($sessionId, [
            'deviceName' => trim($data['device_name']),
        ]);

        $mode = strtolower(trim($data['mode'] ?? 'qr'));

        try {
            if ($mode === 'code') {
                $resp = $this->gateway()->createDeviceWithPairingCode($sessionId, $data['device_name']);
                $pairingCode = $resp['pairing_code'] ?? $resp['pairingCode'] ?? null;
                $this->recordDeviceOwnership($request->user(), $sessionId);

                return redirect()
                    ->route('devices.manage')
                    ->with([
                        'status' => $pairingCode ? "Pairing code untuk {$sessionId}: {$pairingCode}" : "Pairing code dibuat.",
                        'pairingCode' => $pairingCode,
                        'pairingSession' => $sessionId,
                    ]);
            }

            $response = $this->gateway()->startSession($sessionId);
            $qr = $response['qr_image'] ?? $response['qrImage'] ?? $response['qr'] ?? null;
            $this->recordDeviceOwnership($request->user(), $sessionId);

            return redirect()
                ->route('devices.manage')
                ->with([
                    'status' => $qr ? "Device {$sessionId} dibuat, scan QR di bawah." : "Device {$sessionId} berhasil tersambung.",
                    'qr' => $qr,
                    'qrSession' => $sessionId,
                    'autoRefresh' => null,
                ]);
        } catch (Throwable $e) {
            $store->delete($sessionId);
            $msg = $e->getMessage();
            if (str_contains($msg, 'Session already exist')) {
                return redirect()
                    ->route('devices.manage')
                    ->withErrors(['device' => 'Perangkat sudah ditambahkan, coba dengan nomor yang berbeda.']);
            }
            return redirect()
                ->route('devices.manage')
                ->withErrors(['device' => $e->getMessage()]);
        }
    }

    public function createDeviceJson(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_name' => ['required', 'string'],
            'device_phone' => ['required', 'string'],
            'mode' => ['nullable', 'string'],
        ]);

        $sessionId = preg_replace('/\s+/', '', trim($data['device_phone'] ?? ''));
        if (!$sessionId) {
            return response()->json(['ok' => false, 'message' => 'Nomor WA device tidak valid.'], 400);
        }

        $store = $this->sessionConfigStore();
        $existingConfig = $store->get($sessionId);
        $hasConfig = is_array($existingConfig) && count($existingConfig) > 0;

        if ($hasConfig) {
            $store->delete($sessionId);
            try {
                $this->gateway()->deleteSession($sessionId);
            } catch (Throwable) {
                // ignore cleanup failure
            }
        }

        try {
            $sessions = $this->gateway()->listSessions();
            if (in_array($sessionId, $sessions, true)) {
                $this->gateway()->deleteSession($sessionId);
            }
        } catch (Throwable) {
            // ignore
        }

        $store->put($sessionId, [
            'deviceName' => trim($data['device_name']),
        ]);

        $mode = strtolower(trim($data['mode'] ?? 'qr'));

        try {
            if ($mode === 'code') {
                $resp = $this->gateway()->createDeviceWithPairingCode($sessionId, $data['device_name']);
                $pairingCode = $resp['pairing_code'] ?? $resp['pairingCode'] ?? null;
                $this->recordDeviceOwnership($request->user(), $sessionId);

                return response()->json([
                    'ok' => true,
                    'data' => [
                        'device_name' => $data['device_name'],
                        'device' => $sessionId,
                        'pairing_code' => $pairingCode,
                    ],
                ]);
            }

            $response = $this->gateway()->startSession($sessionId);
            $qr = $response['qr_image'] ?? $response['qrImage'] ?? $response['qr'] ?? null;
            $this->recordDeviceOwnership($request->user(), $sessionId);

            return response()->json([
                'ok' => true,
                'data' => [
                    'device_name' => $data['device_name'],
                    'device' => $sessionId,
                    'qr' => $qr,
                    'qr_image' => $qr,
                ],
                ]);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $store->delete($sessionId);
            if (str_contains($msg, 'Session already exist')) {
                return response()->json(['ok' => false, 'message' => 'Perangkat sudah ditambahkan, coba dengan nomor yang berbeda.'], 400);
            }
            $status = str_contains(strtolower($msg), 'timeout') ? 408 : 500;
            return response()->json(['ok' => false, 'message' => $msg], $status);
        }
    }

    public function deleteDevice(Request $request, string $device): RedirectResponse
    {
        if (!$this->canManageSession($request->user(), $device)) {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['device' => "Tidak punya akses ke device {$device}."]);
        }

        try {
            $this->gateway()->deleteSession($device);
            $this->sessionConfigStore()->delete($device);
            DeviceOwnership::where('session_id', $device)->delete();

            return redirect()
                ->route('devices.manage')
                ->with('status', "Device {$device} dihapus.");
        } catch (Throwable $e) {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['device' => $e->getMessage()]);
        }
    }

    public function transferDeviceOwnership(Request $request, string $device): RedirectResponse
    {
        if (!$this->isAdminUser($request->user())) {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['device' => 'Hanya admin yang boleh memindahkan kepemilikan device.']);
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $sessionId = trim($device);
        if ($sessionId === '') {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['device' => 'ID device tidak valid.']);
        }

        DeviceOwnership::updateOrCreate(
            ['session_id' => $sessionId],
            ['user_id' => $data['user_id']]
        );

        return redirect()
            ->route('devices.manage')
            ->with('status', "Kepemilikan device {$sessionId} dipindahkan.");
    }

    public function deviceStatus(Request $request): JsonResponse
    {
        try {
            $sessions = $this->gateway()->listSessions();
            $statuses = $this->gateway()->listSessionStatuses();
            $statusMap = [];

            foreach ($statuses as $row) {
                if (is_array($row) && isset($row['id'])) {
                    $statusMap[$row['id']] = $row;
                }
            }

            $store = $this->sessionConfigStore();
            $devices = [];
            $sessions = $this->filterSessionsForUser($sessions, $request->user());
            foreach ($sessions as $session) {
                $devices[] = [
                    'id' => $session,
                    'status' => $statusMap[$session]['status'] ?? 'disconnected',
                    'user' => $statusMap[$session]['user'] ?? null,
                    'config' => $store->get($session),
                ];
            }

            return response()->json(['devices' => $devices]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function startSession(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'session' => ['required', 'string'],
        ]);

        if (!$this->canManageSession($request->user(), $data['session'])) {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['session' => "Tidak punya akses ke session {$data['session']}."]);
        }

        try {
            $response = $this->gateway()->startSession($data['session']);
            $qr = $response['qr_image'] ?? $response['qrImage'] ?? $response['qr'] ?? null;

            return redirect()
                ->route('devices.manage')
                ->with([
                    'status' => $qr ? 'Session dibuat, scan QR di bawah untuk menghubungkan WhatsApp.' : 'Session berhasil tersambung.',
                    'qr' => $qr,
                    'qrSession' => $data['session'],
                ]);
        } catch (Throwable $e) {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['session' => $e->getMessage()]);
        }
    }

    public function restartSession(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'session' => ['required', 'string'],
        ]);

        if (!$this->canManageSession($request->user(), $data['session'])) {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['session' => "Tidak punya akses ke session {$data['session']}."]);
        }

        try {
            $response = $this->gateway()->restartSession($data['session']);
            $qr = $response['qr_image'] ?? $response['qrImage'] ?? $response['qr'] ?? null;

            return redirect()
                ->route('devices.manage')
                ->with([
                    'status' => $qr ? 'Perangkat direstart, scan QR di bawah jika diperlukan.' : 'Perangkat sedang mencoba menghubungkan kembali.',
                    'qr' => $qr,
                    'qrSession' => $data['session'],
                ]);
        } catch (Throwable $e) {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['session' => $e->getMessage()]);
        }
    }

    public function messageStatuses(Request $request, string $session): JsonResponse
    {
        if (!$this->canManageSession($request->user(), $session)) {
            return response()->json(['ok' => false, 'message' => 'Tidak punya akses ke session ini.'], 403);
        }

        try {
            $data = $this->gateway()->listMessageStatuses($session);
            return response()->json(['ok' => true, 'data' => $data]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function testSendMessage(Request $request, string $session): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
        ]);

        if (!$this->canManageSession($request->user(), $session)) {
            return response()->json(['ok' => false, 'message' => 'Tidak punya akses ke session ini.'], 403);
        }

        try {
            $text = "aplikasi berjalan lancar dan perangkat {$session} berjalan normal. id_device: #6285158663803";
            $res = $this->gateway()->sendTestMessage($session, trim($data['phone']), $text);
            return response()->json(['ok' => true, 'data' => $res]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function closeSession(Request $request, string $session): RedirectResponse
    {
        if (!$this->canManageSession($request->user(), $session)) {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['session' => "Tidak punya akses ke session {$session}."]);
        }

        try {
            $this->gateway()->deleteSession($session);
            $this->sessionConfigStore()->delete($session);
            DeviceOwnership::where('session_id', $session)->delete();

            return redirect()
                ->route('devices.manage')
                ->with('status', "Session {$session} ditutup.");
        } catch (Throwable $e) {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['session' => $e->getMessage()]);
        }
    }

    public function saveSessionConfig(Request $request, string $session): RedirectResponse
    {
        if (!$this->canManageSession($request->user(), $session)) {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['session' => "Tidak punya akses ke session {$session}."]);
        }

        $data = $request->validate([
            'device_name' => ['nullable', 'string'],
            'webhook_base_url' => ['nullable', 'string'],
            'tracking_webhook_base_url' => ['nullable', 'string'],
            'device_status_webhook_base_url' => ['nullable', 'string'],
            'api_key' => ['nullable', 'string'],
            'incoming_enabled' => ['sometimes', 'boolean'],
            'auto_reply_enabled' => ['sometimes', 'boolean'],
            'tracking_enabled' => ['sometimes', 'boolean'],
            'device_status_enabled' => ['sometimes', 'boolean'],
            'anti_spam_enabled' => ['sometimes', 'boolean'],
            'anti_spam_max_per_minute' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'anti_spam_delay_ms' => ['nullable', 'integer', 'min:0', 'max:60000'],
            'anti_spam_interval_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);

        $store = $this->sessionConfigStore();
        $existing = $store->get($session);
        $apiKey = $data['api_key'] ?? null;
        $apiKey = is_string($apiKey) ? trim($apiKey) : $apiKey;
        if ($apiKey === '' || $apiKey === null) {
            $apiKey = $existing['apiKey'] ?? null;
        }

        $deviceName = $data['device_name'] ?? null;
        $deviceName = is_string($deviceName) ? trim($deviceName) : $deviceName;
        if ($deviceName === '') {
            $deviceName = $existing['deviceName'] ?? null;
        }

        $webhookBaseUrl = $data['webhook_base_url'] ?? null;
        $webhookBaseUrl = is_string($webhookBaseUrl) ? trim($webhookBaseUrl) : $webhookBaseUrl;
        if ($webhookBaseUrl === '') {
            $webhookBaseUrl = null;
        }

        $antiSpamEnabled = $request->boolean('anti_spam_enabled');
        $antiSpamMaxPerMinute = max(1, (int) ($data['anti_spam_max_per_minute'] ?? 20));
        $antiSpamDelayMs = max(0, (int) ($data['anti_spam_delay_ms'] ?? 1000));
        $antiSpamIntervalSeconds = max(0, (int) ($data['anti_spam_interval_seconds'] ?? 0));

        $config = [
            'deviceName' => $deviceName,
            'webhookBaseUrl' => $webhookBaseUrl,
            'trackingWebhookBaseUrl' => isset($data['tracking_webhook_base_url']) && trim((string) $data['tracking_webhook_base_url']) !== ''
                ? trim((string) $data['tracking_webhook_base_url'])
                : ($existing['trackingWebhookBaseUrl'] ?? null),
            'deviceStatusWebhookBaseUrl' => isset($data['device_status_webhook_base_url']) && trim((string) $data['device_status_webhook_base_url']) !== ''
                ? trim((string) $data['device_status_webhook_base_url'])
                : ($existing['deviceStatusWebhookBaseUrl'] ?? null),
            'apiKey' => $apiKey,
            'incomingEnabled' => $request->boolean('incoming_enabled'),
            'autoReplyEnabled' => $request->boolean('auto_reply_enabled'),
            'trackingEnabled' => $request->boolean('tracking_enabled'),
            'deviceStatusEnabled' => $request->boolean('device_status_enabled'),
            'antiSpamEnabled' => $antiSpamEnabled,
            'antiSpamMaxPerMinute' => $antiSpamMaxPerMinute,
            'antiSpamDelayMs' => $antiSpamDelayMs,
            'antiSpamIntervalSeconds' => $antiSpamIntervalSeconds,
        ];

        $store->put($session, $config);

        // Simpan anti-spam settings ke database
        DeviceAntiSpamSetting::saveForSession($session, [
            'enabled' => $antiSpamEnabled,
            'max_messages_per_minute' => $antiSpamMaxPerMinute,
            'delay_between_messages_ms' => $antiSpamDelayMs,
            'same_recipient_interval_seconds' => $antiSpamIntervalSeconds,
        ]);

        return redirect()
            ->route('devices.manage')
            ->with('status', "Konfigurasi webhook untuk {$session} disimpan.");
    }

    public function startServer(): RedirectResponse
    {
        try {
            $this->pm2()->start();

            return redirect()
                ->route('dashboard')
                ->with([
                    'status'      => 'Server dijalankan via PM2.',
                    'autoRefresh' => 'server-start',
                ]);
        } catch (Throwable $e) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['server' => $e->getMessage()]);
        }
    }

    public function stopServer(): RedirectResponse
    {
        try {
            $this->pm2()->stop();

            return redirect()
                ->route('dashboard')
                ->with('status', 'Server dihentikan via PM2.');
        } catch (Throwable $e) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['server' => $e->getMessage()]);
        }
    }

    public function restartServer(): RedirectResponse
    {
        try {
            $this->pm2()->restart();

            return redirect()
                ->route('dashboard')
                ->with([
                    'status'      => 'Server di-restart via PM2.',
                    'autoRefresh' => 'server-start',
                ]);
        } catch (Throwable $e) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['server' => $e->getMessage()]);
        }
    }

    public function updateGatewayBase(Request $request): RedirectResponse
    {
        if (!$this->isAdminUser($request->user())) {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['gateway' => 'Hanya admin yang boleh mengubah konfigurasi gateway.']);
        }

        $data = $request->validate([
            'base_url' => ['required', 'string'],
            'api_key' => ['nullable', 'string'],
        ]);

        $baseUrl = trim($data['base_url']);
        $apiKey = isset($data['api_key']) ? trim((string) $data['api_key']) : null;
        $envPath = base_path('.env');

        if ($baseUrl === '') {
            return redirect()->route('devices.manage')->withErrors(['gateway' => 'Base URL tidak boleh kosong.']);
        }

        try {
            $this->writeEnvValue($envPath, 'WA_GATEWAY_BASE', $baseUrl);
            if ($apiKey !== null && $apiKey !== '') {
                $this->writeEnvValue($envPath, 'WA_GATEWAY_KEY', $apiKey);
            }
            return redirect()
                ->route('devices.manage')
                ->with('status', 'Gateway base URL diperbarui. Restart panel jika perlu.');
        } catch (Throwable $e) {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['gateway' => 'Gagal menyimpan base URL: ' . $e->getMessage()]);
        }
    }

    public function updatePasswordResetSessions(Request $request): RedirectResponse
    {
        if (!$this->isAdminUser($request->user())) {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['gateway' => 'Hanya admin yang boleh mengubah konfigurasi reset password.']);
        }

        $data = $request->validate([
            'sessions' => ['nullable', 'array'],
            'sessions.*' => ['string'],
        ]);

        $sessions = array_values(array_filter(array_map('trim', $data['sessions'] ?? [])));
        $envValue = implode(',', $sessions);
        $envPath = base_path('.env');

        try {
            $this->writeEnvValue($envPath, 'PASSWORD_RESET_SESSIONS', $envValue);
            return redirect()
                ->route('devices.manage')
                ->with('status', 'Daftar device reset password diperbarui.');
        } catch (Throwable $e) {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['gateway' => 'Gagal menyimpan device reset password: ' . $e->getMessage()]);
        }
    }

    public function apiStatus(): JsonResponse
    {
        try {
            $health = $this->gateway()->health();

            return response()->json([
                'status' => $health ? 'online' : 'unknown',
                'health' => $health,
                'message' => $health ? null : 'Tidak ada respons dari /health.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'health' => null,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function webhookTest(Request $request, string $session): JsonResponse
    {
        if (!$this->canManageSession($request->user(), $session)) {
            return response()->json(['ok' => false, 'message' => 'Tidak punya akses ke session ini.'], 403);
        }

        $data = $request->validate([
            'type' => ['required', 'string'],
            'url' => ['required', 'string'],
            'api_key' => ['nullable', 'string'],
        ]);

        $type = $data['type'];
        $baseUrl = rtrim(trim($data['url']), '/');
        if ($baseUrl === '') {
            return response()->json(['ok' => false, 'message' => 'URL kosong.'], 400);
        }

        $store = $this->sessionConfigStore();
        $existing = $store->get($session);
        $apiKey = isset($data['api_key']) ? trim((string) $data['api_key']) : '';
        if ($apiKey === '') {
            $apiKey = isset($existing['apiKey']) ? (string) $existing['apiKey'] : '';
        }

        $endpoint = match ($type) {
            'tracking' => '/status',
            'device_status' => '/session',
            default => '/message',
        };

        $payload = match ($type) {
            'tracking' => [
                'session' => $session,
                'message_id' => 'test-message-id',
                'message_status' => 'TEST',
                'tracking_url' => '/message/status?session=' . urlencode($session) . '&id=test-message-id',
            ],
            'device_status' => [
                'session' => $session,
                'status' => 'connecting',
            ],
            default => [
                'session' => $session,
                'from' => 'test',
                'message' => 'test webhook',
                'media' => [
                    'image' => null,
                    'video' => null,
                    'document' => null,
                    'audio' => null,
                ],
            ],
        };

        try {
            $client = Http::timeout(8)->acceptJson()->asJson();
            if ($apiKey !== '') {
                $client = $client->withHeaders(['key' => $apiKey]);
            }

            $resp = $client->post($baseUrl . $endpoint, $payload);
            $ok = $resp->successful();

            return response()->json([
                'ok' => $ok,
                'status' => $resp->status(),
                'message' => $ok
                    ? "OK ({$resp->status()})"
                    : "FAILED ({$resp->status()}): " . substr((string) $resp->body(), 0, 200),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'FAILED: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function listGroups(Request $request, string $session): JsonResponse
    {
        $q = $request->query('q');
        $phone = $request->query('phone');

        if (!$this->canManageSession($request->user(), $session)) {
            return response()->json(['ok' => false, 'message' => 'Tidak punya akses ke session ini.'], 403);
        }

        try {
            $groups = $this->gateway()->listGroups($session, is_string($q) ? $q : null, is_string($phone) ? $phone : null);

            return response()->json([
                'ok' => true,
                'data' => $groups,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function listSessions(Request $request): JsonResponse
    {
        try {
            $sessions = $this->gateway()->listSessions();
            $sessions = $this->filterSessionsForUser($sessions, $request->user());
            return response()->json(['sessions' => $sessions]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function gateway(): GatewayService
    {
        return new GatewayService(
            config('gateway.base_url'),
            config('gateway.api_key')
        );
    }

    private function pm2(): Pm2Service
    {
        return new Pm2Service(
            config('gateway.pm2.app_name'),
            config('gateway.pm2.config_file'),
            config('gateway.pm2.workdir'),
        );
    }

    private function sessionConfigStore(): SessionConfigStore
    {
        return new SessionConfigStore(config('gateway.session_config_path'));
    }

    private function isAdminUser(?User $user): bool
    {
        return $user?->isAdmin() ?? false;
    }

    private function filterSessionsForUser(array $sessions, ?User $user): array
    {
        if ($this->isAdminUser($user)) {
            return $sessions;
        }

        if (!$user) {
            return [];
        }

        $owned = DeviceOwnership::where('user_id', $user->id)->pluck('session_id')->all();
        return array_values(array_intersect($sessions, $owned));
    }

    private function canManageSession(?User $user, string $session): bool
    {
        if ($this->isAdminUser($user)) {
            return true;
        }

        if (!$user) {
            return false;
        }

        return DeviceOwnership::where('user_id', $user->id)
            ->where('session_id', $session)
            ->exists();
    }

    private function recordDeviceOwnership(?User $user, string $sessionId): void
    {
        if (!$user) {
            return;
        }

        DeviceOwnership::updateOrCreate(
            ['session_id' => $sessionId],
            ['user_id' => $user->id]
        );
    }

    private function permissionWarnings(): array
    {
        $warnings = [];
        $user = function_exists('get_current_user') ? get_current_user() : 'web user';

        $sessionConfig = config('gateway.session_config_path');
        $waCredentialsDir = $sessionConfig ? dirname($sessionConfig) : null;
        $mediaDir = dirname(base_path()) . DIRECTORY_SEPARATOR . 'media';

        $checkWritableFile = function (?string $path, string $label) use (&$warnings, $user) {
            if (!$path) {
                return;
            }
            if (!file_exists($path)) {
                $warnings[] = "{$label} belum ada di {$path}. Pastikan {$user} dapat membuatnya (chown/chmod).";
                return;
            }
            if (!is_writable($path)) {
                $warnings[] = "{$label} tidak dapat ditulis ({$path}). Beri izin tulis untuk {$user}.";
            }
        };

        $checkDir = function (?string $path, string $label) use (&$warnings, $user) {
            if (!$path) {
                return;
            }
            if (!is_dir($path)) {
                $warnings[] = "{$label} belum ada di {$path}. Buat folder dan pastikan {$user} bisa menulis.";
                return;
            }
            if (!is_writable($path)) {
                $warnings[] = "{$label} tidak dapat ditulis ({$path}). Beri izin tulis untuk {$user}.";
            }
        };

        $checkDir($waCredentialsDir, 'Folder wa_credentials');
        $checkWritableFile($sessionConfig, 'File session-config.json');
        $checkDir($mediaDir, 'Folder media');

        return $warnings;
    }

    private function writeEnvValue(string $envPath, string $key, string $value): void
    {
        if (!is_file($envPath)) {
            throw new RuntimeException("File .env tidak ditemukan: {$envPath}");
        }

        if (!is_writable($envPath)) {
            throw new RuntimeException("File .env tidak dapat ditulis: {$envPath}");
        }

        $keyPrefix = "{$key}=";
        $lines = preg_split('/\r\n|\r|\n/', file_get_contents($envPath));
        if ($lines === false) {
            throw new RuntimeException('Gagal membaca file .env');
        }

        $updated = [];
        $replaced = false;
        foreach ($lines as $line) {
            if (str_starts_with($line, $keyPrefix)) {
                $updated[] = "{$key}={$value}";
                $replaced = true;
            } elseif ($line !== '') {
                $updated[] = $line;
            }
        }

        if (!$replaced) {
            $updated[] = "{$key}={$value}";
        }

        $content = implode(PHP_EOL, $updated) . PHP_EOL;
        file_put_contents($envPath, $content);
    }

}
