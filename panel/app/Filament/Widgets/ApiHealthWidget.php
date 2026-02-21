<?php

namespace App\Filament\Widgets;

use App\Services\GatewayService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class ApiHealthWidget extends Widget
{
    protected string $view = 'filament.widgets.api-health-widget';
    protected ?string $pollingInterval = '15s';
    protected int|string|array $columnSpan = 1;
    protected static ?int $sort = 3;

    public function copyApiKey(): void
    {
        $key = config('gateway.api_key', '');
        $this->js("navigator.clipboard.writeText('{$key}')");
        Notification::make()->success()->title('API Key berhasil disalin ke clipboard.')->send();
    }

    public function getHealthStatus(): array
    {
        try {
            $gateway = new GatewayService(config('gateway.base_url'), config('gateway.api_key'));
            $health = $gateway->health();

            return [
                'online' => $health !== null,
                'data' => $health,
            ];
        } catch (\Throwable $e) {
            return [
                'online' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
