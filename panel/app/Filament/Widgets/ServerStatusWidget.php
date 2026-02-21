<?php

namespace App\Filament\Widgets;

use App\Services\GatewayService;
use App\Services\Pm2Service;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class ServerStatusWidget extends Widget
{
    protected string $view = 'filament.widgets.server-status-widget';
    protected ?string $pollingInterval = '10s';
    protected int|string|array $columnSpan = 1;
    protected static ?int $sort = 2;

    public function getServerStatus(): array
    {
        $pm2 = $this->pm2();
        $serverStatus = $pm2->status();

        try {
            $gateway = new GatewayService(config('gateway.base_url'), config('gateway.api_key'));
            $health = $gateway->health();
        } catch (\Throwable) {
            $health = null;
        }

        if (! $serverStatus['running'] && $health) {
            $serverStatus['running'] = true;
            $serverStatus['pm2Status'] = 'online (external)';
            $serverStatus['pid'] = null;
        }

        return $serverStatus;
    }

    public function startServer(): void
    {
        try {
            $this->pm2()->start();
            Notification::make()->success()->title('Server dijalankan via PM2.')->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Gagal: ' . $e->getMessage())->send();
        }
    }

    public function stopServer(): void
    {
        try {
            $this->pm2()->stop();
            Notification::make()->success()->title('Server dihentikan via PM2.')->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Gagal: ' . $e->getMessage())->send();
        }
    }

    public function restartServer(): void
    {
        try {
            $this->pm2()->restart();
            Notification::make()->success()->title('Server di-restart via PM2.')->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Gagal: ' . $e->getMessage())->send();
        }
    }

    private function pm2(): Pm2Service
    {
        return new Pm2Service(
            config('gateway.pm2.app_name'),
            config('gateway.pm2.config_file'),
            config('gateway.pm2.workdir'),
        );
    }
}
