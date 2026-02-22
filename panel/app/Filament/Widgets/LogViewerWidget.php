<?php

namespace App\Filament\Widgets;

use App\Services\Pm2Service;
use Filament\Widgets\Widget;

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

        $logFile = $pm2->getLogFile();
        if (! is_string($logFile) || $logFile === '' || ! is_readable($logFile) || filesize($logFile) === 0) {
            return null;
        }

        try {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            return implode("\n", array_slice($lines, -30));
        } catch (\Throwable) {
            return null;
        }
    }
}
