<?php

namespace App\Filament\Widgets;

use App\Services\Pm2Service;
use Filament\Widgets\Widget;
use Symfony\Component\Process\Process;

class LogViewerWidget extends Widget
{
    protected string $view = 'filament.widgets.log-viewer-widget';
    protected ?string $pollingInterval = '5s';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getLogTail(): ?string
    {
        $pm2 = new Pm2Service(
            config('gateway.pm2.app_name'),
            config('gateway.pm2.config_file'),
            config('gateway.pm2.workdir'),
            config('gateway.pm2.binary', 'pm2'),
            config('gateway.pm2.run_as_user', ''),
        );

        $logFile = $this->resolveLogFile($pm2->getLogFile());
        if ($logFile === null) {
            return null;
        }

        try {
            if (str_ends_with($logFile, '.gz')) {
                $process = Process::fromShellCommandline(
                    'gzip -cd -- ' . escapeshellarg($logFile) . ' | tail -n 30'
                );
                $process->setTimeout(5);
                $process->run();
                if (! $process->isSuccessful()) {
                    return null;
                }

                $out = trim($process->getOutput());
                return $out !== '' ? $out : null;
            }

            $process = new Process(['tail', '-n', '30', $logFile]);
            $process->setTimeout(5);
            $process->run();
            if (! $process->isSuccessful()) {
                return null;
            }

            $out = trim($process->getOutput());
            return $out !== '' ? $out : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveLogFile(string $primaryLogFile): ?string
    {
        if ($primaryLogFile === '') {
            return null;
        }

        $candidates = [];

        if (is_readable($primaryLogFile) && filesize($primaryLogFile) > 0) {
            return $primaryLogFile;
        }

        $rotated = glob($primaryLogFile . '*') ?: [];
        foreach ($rotated as $file) {
            if ($file === $primaryLogFile) {
                continue;
            }
            if (! is_file($file) || ! is_readable($file)) {
                continue;
            }
            if (filesize($file) <= 0) {
                continue;
            }
            $candidates[] = $file;
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        return $candidates[0] ?? null;
    }
}
