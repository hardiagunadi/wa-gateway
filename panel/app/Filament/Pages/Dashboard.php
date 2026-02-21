<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ApiHealthWidget;
use App\Filament\Widgets\LogViewerWidget;
use App\Filament\Widgets\PermissionWarningsWidget;
use App\Filament\Widgets\ServerStatusWidget;
use App\Filament\Widgets\SessionStatsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';
    protected static ?string $title = 'Dashboard';

    public function getWidgets(): array
    {
        return [
            SessionStatsWidget::class,
            ServerStatusWidget::class,
            ApiHealthWidget::class,
            PermissionWarningsWidget::class,
            LogViewerWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
