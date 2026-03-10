<?php

namespace App\Filament\Pages;

use App\Services\GatewayService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use RuntimeException;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-8-tooth';
    protected static ?string $title = 'Pengaturan';
    protected static ?string $navigationLabel = 'Pengaturan';
    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';
    protected string $view = 'filament.pages.settings';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public ?array $gatewayData = [];
    public ?array $resetData = [];

    public function mount(): void
    {
        $this->gatewayForm->fill([
            'base_url' => config('gateway.base_url', ''),
            'api_key' => config('gateway.api_key', ''),
        ]);

        $this->resetForm->fill([
            'sessions' => config('gateway.password_reset_sessions', []),
        ]);
    }

    protected function getForms(): array
    {
        return [
            'gatewayForm',
            'resetForm',
        ];
    }

    public function gatewayForm(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Konfigurasi Gateway')->schema([
                    TextInput::make('base_url')
                        ->label('Base URL')
                        ->required()
                        ->placeholder('http://localhost:5001'),
                    TextInput::make('api_key')
                        ->label('API Key')
                        ->password()
                        ->revealable(),
                ]),
            ])
            ->statePath('gatewayData');
    }

    public function resetForm(Schema $form): Schema
    {
        $sessions = $this->getAvailableSessions();

        return $form
            ->schema([
                Section::make('Device Reset Password')->schema([
                    CheckboxList::make('sessions')
                        ->label('Session yang digunakan untuk reset password via WhatsApp')
                        ->options(array_combine($sessions, $sessions))
                        ->columns(2),
                ])->description('Pilih session/device yang akan digunakan untuk mengirim password reset.'),
            ])
            ->statePath('resetData');
    }

    public function saveGateway(): void
    {
        $data = $this->gatewayForm->getState();
        $envPath = base_path('.env');

        $baseUrl = trim($data['base_url'] ?? '');
        if ($baseUrl === '') {
            Notification::make()->danger()->title('Base URL tidak boleh kosong.')->send();
            return;
        }

        try {
            $this->writeEnvValue($envPath, 'WA_GATEWAY_BASE', $baseUrl);
            $apiKey = trim($data['api_key'] ?? '');
            if ($apiKey !== '') {
                $this->writeEnvValue($envPath, 'WA_GATEWAY_KEY', $apiKey);
            }
            Notification::make()->success()->title('Konfigurasi gateway disimpan.')->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Gagal: ' . $e->getMessage())->send();
        }
    }

    public function saveResetSessions(): void
    {
        $data = $this->resetForm->getState();
        $sessions = array_values(array_filter(array_map('trim', $data['sessions'] ?? [])));
        $envValue = implode(',', $sessions);
        $envPath = base_path('.env');

        try {
            $this->writeEnvValue($envPath, 'PASSWORD_RESET_SESSIONS', $envValue);
            Notification::make()->success()->title('Daftar device reset password disimpan.')->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Gagal: ' . $e->getMessage())->send();
        }
    }

    private function getAvailableSessions(): array
    {
        try {
            $gateway = new GatewayService(config('gateway.base_url'), config('gateway.api_key'));
            return $gateway->listSessions();
        } catch (\Throwable) {
            return [];
        }
    }

    private function writeEnvValue(string $envPath, string $key, string $value): void
    {
        if (! is_file($envPath)) {
            throw new RuntimeException("File .env tidak ditemukan: {$envPath}");
        }

        if (! is_writable($envPath)) {
            throw new RuntimeException("File .env tidak dapat ditulis: {$envPath}");
        }

        $keyPrefix = "{$key}=";
        $lines = preg_split('/\r\n|\r|\n/', file_get_contents($envPath));
        if ($lines === false) {
            throw new RuntimeException('Gagal membaca file .env');
        }

        $updated = [];
        $replaced = false;
        foreach ($lines as $line) {
            if (str_starts_with($line, $keyPrefix)) {
                $updated[] = "{$key}={$value}";
                $replaced = true;
            } elseif ($line !== '') {
                $updated[] = $line;
            }
        }

        if (! $replaced) {
            $updated[] = "{$key}={$value}";
        }

        $content = implode(PHP_EOL, $updated) . PHP_EOL;
        file_put_contents($envPath, $content);
    }
}
