<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class PermissionWarningsWidget extends Widget
{
    protected string $view = 'filament.widgets.permission-warnings-widget';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getWarnings(): array
    {
        $warnings = [];

        $sessionConfig = config('gateway.session_config_path');
        $waCredentialsDir = $sessionConfig ? dirname($sessionConfig) : null;
        $mediaDir = dirname(base_path()) . DIRECTORY_SEPARATOR . 'media';

        if ($waCredentialsDir) {
            if (! is_dir($waCredentialsDir)) {
                $warnings[] = "Folder wa_credentials belum ada di {$waCredentialsDir}.";
            } elseif (! is_writable($waCredentialsDir)) {
                $warnings[] = "Folder wa_credentials tidak dapat ditulis ({$waCredentialsDir}).";
            }
        }

        if ($sessionConfig) {
            if (file_exists($sessionConfig) && ! is_writable($sessionConfig)) {
                $warnings[] = "File session-config.json tidak dapat ditulis ({$sessionConfig}).";
            }
        }

        if (! is_dir($mediaDir)) {
            $warnings[] = "Folder media belum ada di {$mediaDir}.";
        } elseif (! is_writable($mediaDir)) {
            $warnings[] = "Folder media tidak dapat ditulis ({$mediaDir}).";
        }

        return $warnings;
    }
}
