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
}

