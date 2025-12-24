<?php

namespace App\Services;

class DeviceRegistry
{
    public function __construct(private readonly string $path)
    {
    }

    public function getRecordBySession(string $session): ?array
    {
        $all = $this->read();
        foreach ($all as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (($row['sessionId'] ?? null) === $session) {
                return $row;
            }
        }

        return null;
    }

    public function getTokenBySession(string $session): ?string
    {
        $record = $this->getRecordBySession($session);
        if (!$record) {
            return null;
        }

        $token = $record['token'] ?? $record['apiKey'] ?? null;
        $token = is_string($token) ? trim($token) : null;

        return $token !== '' ? $token : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function read(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $raw = file_get_contents($this->path);
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
