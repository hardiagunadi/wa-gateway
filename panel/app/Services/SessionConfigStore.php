<?php

namespace App\Services;

class SessionConfigStore
{
    public function __construct(private readonly string $path)
    {
    }

    public function get(string $session): array
    {
        $all = $this->all();
        return $all[$session] ?? [];
    }

    public function all(): array
    {
        if (!file_exists($this->path)) {
            return [];
        }

        $raw = file_get_contents($this->path);
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    public function put(string $session, array $config): void
    {
        $all = $this->all();
        $all[$session] = $config;

        $this->write($all);
        $this->syncDeviceRegistryToken($session, $config);
    }

    public function delete(string $session): void
    {
        $all = $this->all();
        if (array_key_exists($session, $all)) {
            unset($all[$session]);
            $this->write($all);
        }
    }

    private function write(array $data): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->path, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function syncDeviceRegistryToken(string $session, array $config): void
    {
        $token = isset($config['apiKey']) ? trim((string) $config['apiKey']) : '';
        if ($token === '') {
            return;
        }

        $registryPath = $this->registryPath();
        $records = $this->readRegistry($registryPath);

        $existing = null;
        $filtered = [];
        foreach ($records as $row) {
            $rowSession = isset($row['sessionId']) ? (string) $row['sessionId'] : '';
            $rowToken = isset($row['token']) ? (string) $row['token'] : '';
            if ($rowToken === '' && isset($row['apiKey'])) {
                $rowToken = (string) $row['apiKey'];
            }

            if ($rowSession === $session) {
                if ($existing === null) {
                    $existing = $row;
                }
                continue;
            }
            if ($rowToken !== '' && $rowToken === $token) {
                continue;
            }

            $filtered[] = $row;
        }

        $next = is_array($existing) ? $existing : [];
        $next['token'] = $token;
        $next['apiKey'] = $token;
        $next['sessionId'] = $session;
        if (!isset($next['createdAt']) || trim((string) $next['createdAt']) === '') {
            $next['createdAt'] = date('c');
        }

        $deviceName = isset($config['deviceName']) ? trim((string) $config['deviceName']) : '';
        if ($deviceName !== '') {
            $next['name'] = $deviceName;
        }

        $webhookUrl = isset($config['webhookBaseUrl']) ? trim((string) $config['webhookBaseUrl']) : '';
        if ($webhookUrl !== '') {
            $next['webhookUrl'] = $webhookUrl;
        }

        $trackingUrl = isset($config['trackingWebhookBaseUrl']) ? trim((string) $config['trackingWebhookBaseUrl']) : '';
        if ($trackingUrl !== '') {
            $next['trackingBaseUrl'] = $trackingUrl;
        }

        $filtered[] = $next;

        $dir = dirname($registryPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $registryPath,
            json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function registryPath(): string
    {
        return dirname($this->path) . DIRECTORY_SEPARATOR . 'device-registry.json';
    }

    private function readRegistry(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return [];
        }

        if (array_is_list($data)) {
            return $data;
        }

        $rows = [];
        foreach ($data as $sessionId => $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!isset($row['sessionId']) || trim((string) $row['sessionId']) === '') {
                $row['sessionId'] = (string) $sessionId;
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
