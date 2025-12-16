<?php

namespace App\Services;

class NodeEnvManager
{
    public function __construct(private readonly string $path)
    {
    }

    public function read(): array
    {
        if (!file_exists($this->path)) {
            return [];
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES);
        $data = [];

        foreach ($lines as $line) {
            if (!is_string($line) || str_starts_with(trim($line), '#') || trim($line) === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $data[$key] = $this->unquote($value);
        }

        return $data;
    }

    public function update(array $values): array
    {
        $current = $this->read();

        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                unset($current[$key]);
                continue;
            }

            $current[$key] = $value;
        }

        $buffer = '';
        foreach ($current as $key => $value) {
            $buffer .= $key . '=' . $this->quoteIfNeeded($value) . PHP_EOL;
        }

        file_put_contents($this->path, $buffer);

        return $current;
    }

    private function unquote(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        if ((str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"')) ||
            (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'"))
        ) {
            return substr($trimmed, 1, -1);
        }

        return $trimmed;
    }

    private function quoteIfNeeded(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (preg_match('/\\s|#|=|["\']/', $value)) {
            return '"' . str_replace('"', '\\"', $value) . '"';
        }

        return $value;
    }
}
