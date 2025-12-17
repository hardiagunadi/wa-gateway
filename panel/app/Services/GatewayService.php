<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class GatewayService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey = null
    ) {
    }

    public function listSessions(): array
    {
        $response = $this->client()->get('/session');
        $response->throw();

        $data = $response->json('data');

        if (is_array($data)) {
            if (array_is_list($data)) {
                return array_values(array_map('strval', $data));
            }

            // Support APIs that return an object keyed by session id.
            return array_map('strval', array_keys($data));
        }

        return [];
    }

    public function listSessionStatuses(): array
    {
        $response = $this->client()->get('/session/status');
        $response->throw();

        $data = $response->json('data');

        return is_array($data) ? $data : [];
    }

    public function listGroups(string $session, ?string $q = null, ?string $phone = null): array
    {
        $params = ['session' => $session];
        if (is_string($q) && trim($q) !== '') {
            $params['q'] = trim($q);
        }
        if (is_string($phone) && trim($phone) !== '') {
            $params['phone'] = trim($phone);
        }

        $response = $this->client()->get('/group/list', $params);
        $response->throw();

        $data = $response->json('data');
        return is_array($data) ? $data : [];
    }

    public function startSession(string $session): array
    {
        $response = $this->client()->post('/session/start', [
            'session' => $session,
        ]);
        $response->throw();

        return $response->json() ?? [];
    }

    public function logoutSession(string $session): void
    {
        $response = $this->client()->post('/session/logout', [
            'session' => $session,
        ]);
        $response->throw();
    }

    public function deleteSession(string $session): void
    {
        $response = $this->client()->post('/session/delete', [
            'session' => $session,
        ]);
        $response->throw();
    }

    public function restartSession(string $session): array
    {
        $response = $this->client()->post('/session/restart', [
            'session' => $session,
        ]);
        $response->throw();

        return $response->json() ?? [];
    }

    public function health(): ?array
    {
        try {
            $response = $this->client()->get('/health');
            $response->throw();

            return $response->json();
        } catch (RequestException) {
            return null;
        }
    }

    private function client()
    {
        $client = Http::baseUrl($this->baseUrl)->asJson();

        if (!empty($this->apiKey)) {
            $client = $client->withHeader('key', $this->apiKey);
        }

        return $client;
    }
}
