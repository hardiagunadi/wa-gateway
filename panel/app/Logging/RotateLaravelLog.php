<?php

namespace App\Logging;

use Monolog\Logger;

class RotateLaravelLog
{
    private const LOG_ROTATE_BYTES = 5 * 1024 * 1024;

    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            if (!method_exists($handler, 'getUrl')) {
                continue;
            }

            $path = $handler->getUrl();
            if (!is_string($path) || !$this->isFilePath($path)) {
                continue;
            }

            if ($this->rotateLogIfNeeded($path)) {
                $handler->close();
            }
        }
    }

    private function isFilePath(string $path): bool
    {
        return !str_contains($path, '://');
    }

    private function rotateLogIfNeeded(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        $size = filesize($path);
        if ($size === false || $size <= self::LOG_ROTATE_BYTES) {
            return false;
        }

        $dir = dirname($path);
        $base = pathinfo($path, PATHINFO_FILENAME);
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $timestamp = date('Ymd-His');

        $suffix = 0;
        do {
            $suffix += 1;
            $suffixPart = $suffix > 1 ? '-' . $suffix : '';
            $extPart = $ext !== '' ? '.' . $ext : '';
            $rotated = $dir . DIRECTORY_SEPARATOR . $base . '-' . $timestamp . $suffixPart . $extPart;
        } while (file_exists($rotated));

        return @rename($path, $rotated);
    }
}
