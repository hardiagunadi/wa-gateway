<?php

namespace App\Http\Controllers;

use App\Models\DeviceOwnership;
use App\Models\User;
use App\Services\GatewayService;
use App\Services\SessionConfigStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Throwable;

class GatewayController extends Controller
{
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

}
