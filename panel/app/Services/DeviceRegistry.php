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

        if (!is_array($decoded)) {
            return [];
        }

        // Support both array-of-records and object keyed by sessionId.
        if (array_is_list($decoded)) {
            return $decoded;
        }

        $list = [];
        foreach ($decoded as $sessionId => $row) {
            if (!is_array($row)) {
                continue;
            }

            $list[] = array_merge(
                ['sessionId' => is_string($row['sessionId'] ?? null) && $row['sessionId'] !== '' ? $row['sessionId'] : $sessionId],
                $row
            );
        }

        return $list;
    }
}
