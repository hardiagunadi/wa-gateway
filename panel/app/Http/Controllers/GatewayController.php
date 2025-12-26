<?php

namespace App\Http\Controllers;

use App\Services\GatewayService;
use App\Services\NpmService;
use App\Services\SessionConfigStore;
use App\Services\DeviceRegistry;
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

        try {
            $sessions = $gateway->listSessions();
            $health = $gateway->health();
            $sessionStatuses = $gateway->listSessionStatuses();
        } catch (Throwable $e) {
            $apiError = $e->getMessage();
        }

        $npm = $this->npm();
        $store = $this->sessionConfigStore();
        foreach ($sessions as $session) {
            $sessionConfigs[$session] = $store->get($session);
        }

        $npmStatus = $npm->status();
        if (!$npmStatus['running'] && $health) {
            // Infer running when API is reachable even if pid file is absent (e.g., started via pm2/systemd).
            $npmStatus['running'] = true;
            $npmStatus['pid'] = null;
            $npmStatus['source'] = 'inferred';
        }

        $logTail = null;
        $logFile = $npmStatus['logFile'] ?? null;
        if (is_string($logFile) && $logFile !== '' && is_readable($logFile) && filesize($logFile) > 0) {
            try {
                $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                $logTail = implode("\n", array_slice($lines, -30));
            } catch (Throwable) {
                $logTail = null;
            }
        }

        return view('dashboard', [
            'sessions' => $sessions,
            'health' => $health,
            'apiError' => $apiError,
            'sessionStatuses' => $sessionStatuses,
            'qrData' => session('qr'),
            'qrSession' => session('qrSession'),
            'pairingCode' => session('pairingCode'),
            'pairingSession' => session('pairingSession'),
            'statusMessage' => session('status'),
            'autoRefresh' => session('autoRefresh'),
            'errorsBag' => $request->session()->get('errors'),
            'npmStatus' => $npmStatus,
            'sessionConfigs' => $sessionConfigs,
            'gatewayConfig' => [
                'base' => config('gateway.base_url'),
                'key' => config('gateway.api_key'),
            ],
            'logTail' => $logTail,
            'tokenTargets' => $this->tokenTargets(),
            'permissionWarnings' => $this->permissionWarnings(),
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

        try {
            $sessions = $gateway->listSessions();
            $health = $gateway->health();
            $sessionStatuses = $gateway->listSessionStatuses();
        } catch (Throwable $e) {
            $apiError = $e->getMessage();
        }

        $store = $this->sessionConfigStore();
        foreach ($sessions as $session) {
            $sessionConfigs[$session] = $store->get($session);
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
            'gatewayConfig' => [
                'base' => config('gateway.base_url'),
                'key' => config('gateway.api_key'),
            ],
            'tokenTargets' => $this->tokenTargets(),
            'permissionWarnings' => $this->permissionWarnings(),
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

    public function deleteDevice(string $device): RedirectResponse
    {
        try {
            $this->gateway()->deleteSession($device);
            $this->sessionConfigStore()->delete($device);

            return redirect()
                ->route('devices.manage')
                ->with('status', "Device {$device} dihapus.");
        } catch (Throwable $e) {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['device' => $e->getMessage()]);
        }
    }

    public function deviceStatus(): JsonResponse
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

    public function messageStatuses(string $session): JsonResponse
    {
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

        try {
            $text = "aplikasi berjalan lancar dan perangkat {$session} berjalan normal. id_device: #6285158663803";
            $res = $this->gateway()->sendTestMessage($session, trim($data['phone']), $text);
            return response()->json(['ok' => true, 'data' => $res]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function closeSession(string $session): RedirectResponse
    {
        try {
            $this->gateway()->deleteSession($session);
            $this->sessionConfigStore()->delete($session);

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
        $data = $request->validate([
            'device_name' => ['nullable', 'string'],
            'webhook_base_url' => ['required', 'string'],
            'tracking_webhook_base_url' => ['nullable', 'string'],
            'device_status_webhook_base_url' => ['nullable', 'string'],
            'api_key' => ['nullable', 'string'],
            'incoming_enabled' => ['sometimes', 'boolean'],
            'auto_reply_enabled' => ['sometimes', 'boolean'],
            'tracking_enabled' => ['sometimes', 'boolean'],
            'device_status_enabled' => ['sometimes', 'boolean'],
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

        $config = [
            'deviceName' => $deviceName,
            'webhookBaseUrl' => $data['webhook_base_url'],
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
        ];

        $store->put($session, $config);

        return redirect()
            ->route('devices.manage')
            ->with('status', "Konfigurasi webhook untuk {$session} disimpan.");
    }

    public function startServer(): RedirectResponse
    {
        try {
            $pid = $this->npm()->start();

            return redirect()
                ->route('dashboard')
                ->with([
                    'status' => "NPM server dijalankan (PID {$pid}).",
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
            $this->npm()->stop();

            return redirect()
                ->route('dashboard')
                ->with('status', 'NPM server dihentikan.');
        } catch (Throwable $e) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['server' => $e->getMessage()]);
        }
    }

    public function syncToken(Request $request, string $device): RedirectResponse
    {
        $targets = $this->tokenTargets();
        $targetKey = $request->input('target');

        if (!is_string($targetKey) || !isset($targets[$targetKey])) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['device' => 'Target aplikasi tidak dikenal.']);
        }

        $target = $targets[$targetKey];
        $envPath = $target['env_path'] ?? null;
        $envKey = $target['env_key'] ?? 'WA_GATEWAY_TOKEN';
        $allowed = $target['allowed_sessions'] ?? [];
        $label = $target['label'] ?? ucfirst($targetKey);

        if (!$envPath) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['device' => 'Lokasi .env target belum dikonfigurasi.']);
        }

        if (is_array($allowed) && count($allowed) > 0 && !in_array($device, $allowed, true)) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['device' => "Device {$device} tidak diizinkan untuk target {$label}."]);
        }

        $registryPath = config('gateway.registry_path');
        $registry = new DeviceRegistry($registryPath);
        $token = $registry->getTokenBySession($device);

        if (!$token) {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['device' => "Token untuk device {$device} tidak ditemukan di registry."]);
        }

        try {
            $this->writeEnvValue($envPath, $envKey, $token);
        } catch (Throwable $e) {
            return redirect()
                ->route('devices.manage')
                ->withErrors(['device' => "Gagal menyimpan token ke {$envPath}: " . $e->getMessage()]);
        }

        return redirect()
            ->route('devices.manage')
            ->with('status', "Token {$envKey} untuk {$label} diperbarui dari device {$device}.");
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

    public function listSessions(): JsonResponse
    {
        try {
            $sessions = $this->gateway()->listSessions();

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

    private function npm(): NpmService
    {
        [$command, $workingDir] = $this->resolveNodeServerCommand();

        return new NpmService($command, $workingDir);
    }

    private function sessionConfigStore(): SessionConfigStore
    {
        return new SessionConfigStore(config('gateway.session_config_path'));
    }

    private function permissionWarnings(): array
    {
        $warnings = [];
        $user = function_exists('get_current_user') ? get_current_user() : 'web user';

        $sessionConfig = config('gateway.session_config_path');
        $registryPath = config('gateway.registry_path');
        $waCredentialsDir = $sessionConfig ? dirname($sessionConfig) : null;
        $mediaDir = dirname(base_path()) . DIRECTORY_SEPARATOR . 'media';
        $targetEnv = config('gateway.token_targets.jadwal.env_path') ?? null;

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
        $checkWritableFile($registryPath, 'File device-registry.json');
        $checkDir($mediaDir, 'Folder media');
        $checkWritableFile($targetEnv, 'File .env target sinkron token');

        return $warnings;
    }

    private function tokenTargets(): array
    {
        $targets = config('gateway.token_targets', []);
        if (!is_array($targets)) {
            return [];
        }

        // Only expose configured targets with env path defined.
        return array_filter($targets, function ($target) {
            return is_array($target) && !empty($target['env_path']);
        });
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

    /**
     * Build command for the Node server and resolve the working directory even
     * when the absolute path differs between environments.
     */
    private function resolveNodeServerCommand(): array
    {
        $workdirCandidates = array_filter([
            config('gateway.npm.workdir'),
            dirname(base_path()),
            base_path(),
        ]);

        foreach ($workdirCandidates as $candidate) {
            $resolvedDir = $this->absolutePath($candidate);
            $loader = $resolvedDir . DIRECTORY_SEPARATOR . 'node_modules/tsx/dist/loader.mjs';
            $entry = $resolvedDir . DIRECTORY_SEPARATOR . 'src/index.ts';

            if (is_file($loader) && is_file($entry)) {
                $defaultCommand = 'node --import ' . escapeshellarg($loader) . ' ' . escapeshellarg($entry);
                $envCommand = $this->sanitizedEnvCommand();

                return [$envCommand ?: $defaultCommand, $resolvedDir];
            }
        }

        throw new RuntimeException('Tidak dapat menemukan direktori server Node. Pastikan src/index.ts dan node_modules/tsx/dist/loader.mjs tersedia.');
    }

    private function absolutePath(string $path): string
    {
        $absolute = str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);

        return realpath($absolute) ?: $absolute;
    }

    private function sanitizedEnvCommand(): ?string
    {
        $envCommand = config('gateway.npm.command');
        $envCommand = is_string($envCommand) ? trim($envCommand) : '';

        if ($envCommand === '') {
            return null;
        }

        // Ignore legacy default so new node --import flow is used automatically.
        if (stripos($envCommand, 'npm run dev') !== false) {
            return null;
        }

        return $envCommand;
    }
}
