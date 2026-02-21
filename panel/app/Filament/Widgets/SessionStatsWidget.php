<?php

namespace App\Filament\Widgets;

use App\Models\DeviceOwnership;
use App\Services\GatewayService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SessionStatsWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '15s';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        try {
            $gateway = new GatewayService(config('gateway.base_url'), config('gateway.api_key'));
            $sessions = $gateway->listSessions();
            $statuses = $gateway->listSessionStatuses();
        } catch (\Throwable) {
            return [
                Stat::make('Total Perangkat', '-')->description('Gateway tidak tersedia')->color('danger'),
            ];
        }

        $user = auth()->user();
        if (! $user->isAdmin()) {
            $owned = DeviceOwnership::where('user_id', $user->id)->pluck('session_id')->all();
            $sessions = array_values(array_intersect($sessions, $owned));
        }

        $statusMap = collect($statuses)->keyBy('id');
        $connected = 0;
        foreach ($sessions as $session) {
            $status = $statusMap->get($session);
            if (is_array($status) && ($status['status'] ?? '') === 'connected') {
                $connected++;
            }
        }

        $total = count($sessions);
        $disconnected = $total - $connected;

        return [
            Stat::make('Total Perangkat', $total)
                ->icon('heroicon-o-device-phone-mobile'),
            Stat::make('Terhubung', $connected)
                ->color('success')
                ->icon('heroicon-o-signal'),
            Stat::make('Terputus', $disconnected)
                ->color('danger')
                ->icon('heroicon-o-signal-slash'),
        ];
    }
}
