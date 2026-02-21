<?php

namespace App\Filament\Pages;

use App\Models\DeviceAntiSpamSetting;
use App\Models\DeviceOwnership;
use App\Services\GatewayService;
use App\Services\SessionConfigStore;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Http;

class DeviceConfig extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected string $view = 'filament.pages.device-config';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'devices/{session}/config';

    public string $session = '';

    // Config form data
    public ?array $configData = [];

    // Test send form data
    public string $testPhone = '';

    public function mount(string $session): void
    {
        $this->session = $session;

        if (! $this->canManageSession()) {
            abort(403, 'Tidak punya akses ke session ini.');
        }

        $store = $this->sessionConfigStore();
        $config = $store->get($session);
        $antiSpam = DeviceAntiSpamSetting::getForSession($session);

        $this->configForm->fill([
            'device_name' => $config['deviceName'] ?? '',
            'webhook_base_url' => $config['webhookBaseUrl'] ?? '',
            'tracking_webhook_base_url' => $config['trackingWebhookBaseUrl'] ?? '',
            'device_status_webhook_base_url' => $config['deviceStatusWebhookBaseUrl'] ?? '',
            'api_key' => $config['apiKey'] ?? '',
            'incoming_enabled' => $config['incomingEnabled'] ?? false,
            'auto_reply_enabled' => $config['autoReplyEnabled'] ?? false,
            'tracking_enabled' => $config['trackingEnabled'] ?? false,
            'device_status_enabled' => $config['deviceStatusEnabled'] ?? false,
            'anti_spam_enabled' => $antiSpam['enabled'] ?? false,
            'anti_spam_max_per_minute' => $antiSpam['max_messages_per_minute'] ?? 20,
            'anti_spam_delay_ms' => $antiSpam['delay_between_messages_ms'] ?? 1000,
            'anti_spam_interval_seconds' => $antiSpam['same_recipient_interval_seconds'] ?? 0,
        ]);
    }

    public function getTitle(): string
    {
        return "Konfigurasi: {$this->session}";
    }

    protected function getForms(): array
    {
        return [
            'configForm',
        ];
    }

    public function configForm(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Informasi Perangkat')
                    ->schema([
                        TextInput::make('device_name')
                            ->label('Nama Perangkat'),
                        TextInput::make('api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable(),
                    ])
                    ->columns(2),

                Section::make('Webhook')
                    ->schema([
                        TextInput::make('webhook_base_url')
                            ->label('Webhook Pesan Masuk')
                            ->url()
                            ->placeholder('https://example.com/webhook'),
                        Toggle::make('incoming_enabled')
                            ->label('Aktifkan Webhook Pesan Masuk'),
                        Toggle::make('auto_reply_enabled')
                            ->label('Aktifkan Auto Reply'),
                        TextInput::make('tracking_webhook_base_url')
                            ->label('Webhook Tracking')
                            ->url()
                            ->placeholder('https://example.com/tracking'),
                        Toggle::make('tracking_enabled')
                            ->label('Aktifkan Tracking'),
                        TextInput::make('device_status_webhook_base_url')
                            ->label('Webhook Status Device')
                            ->url()
                            ->placeholder('https://example.com/device-status'),
                        Toggle::make('device_status_enabled')
                            ->label('Aktifkan Webhook Status Device'),
                    ]),

                Section::make('Anti-Spam')
                    ->schema([
                        Toggle::make('anti_spam_enabled')
                            ->label('Aktifkan Anti-Spam'),
                        Grid::make(3)->schema([
                            TextInput::make('anti_spam_max_per_minute')
                                ->label('Max Pesan/Menit')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(1000),
                            TextInput::make('anti_spam_delay_ms')
                                ->label('Delay (ms)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(60000),
                            TextInput::make('anti_spam_interval_seconds')
                                ->label('Interval Penerima Sama (detik)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(86400),
                        ]),
                    ]),
            ])
            ->statePath('configData');
    }

    public function saveConfig(): void
    {
        $data = $this->configForm->getState();
        $store = $this->sessionConfigStore();
        $existing = $store->get($this->session);

        $apiKey = trim($data['api_key'] ?? '');
        if ($apiKey === '') {
            $apiKey = $existing['apiKey'] ?? null;
        }

        $deviceName = trim($data['device_name'] ?? '');
        if ($deviceName === '') {
            $deviceName = $existing['deviceName'] ?? null;
        }

        $webhookBaseUrl = trim($data['webhook_base_url'] ?? '');
        if ($webhookBaseUrl === '') {
            $webhookBaseUrl = null;
        }

        $config = [
            'deviceName' => $deviceName,
            'webhookBaseUrl' => $webhookBaseUrl,
            'trackingWebhookBaseUrl' => trim($data['tracking_webhook_base_url'] ?? '') ?: ($existing['trackingWebhookBaseUrl'] ?? null),
            'deviceStatusWebhookBaseUrl' => trim($data['device_status_webhook_base_url'] ?? '') ?: ($existing['deviceStatusWebhookBaseUrl'] ?? null),
            'apiKey' => $apiKey,
            'incomingEnabled' => $data['incoming_enabled'] ?? false,
            'autoReplyEnabled' => $data['auto_reply_enabled'] ?? false,
            'trackingEnabled' => $data['tracking_enabled'] ?? false,
            'deviceStatusEnabled' => $data['device_status_enabled'] ?? false,
            'antiSpamEnabled' => $data['anti_spam_enabled'] ?? false,
            'antiSpamMaxPerMinute' => max(1, (int) ($data['anti_spam_max_per_minute'] ?? 20)),
            'antiSpamDelayMs' => max(0, (int) ($data['anti_spam_delay_ms'] ?? 1000)),
            'antiSpamIntervalSeconds' => max(0, (int) ($data['anti_spam_interval_seconds'] ?? 0)),
        ];

        $store->put($this->session, $config);

        DeviceAntiSpamSetting::saveForSession($this->session, [
            'enabled' => $config['antiSpamEnabled'],
            'max_messages_per_minute' => $config['antiSpamMaxPerMinute'],
            'delay_between_messages_ms' => $config['antiSpamDelayMs'],
            'same_recipient_interval_seconds' => $config['antiSpamIntervalSeconds'],
        ]);

        Notification::make()
            ->success()
            ->title("Konfigurasi untuk {$this->session} disimpan.")
            ->send();
    }

    public function testWebhook(string $type): void
    {
        $data = $this->configForm->getState();

        $urlField = match ($type) {
            'tracking' => 'tracking_webhook_base_url',
            'device_status' => 'device_status_webhook_base_url',
            default => 'webhook_base_url',
        };

        $baseUrl = rtrim(trim($data[$urlField] ?? ''), '/');
        if ($baseUrl === '') {
            Notification::make()->danger()->title('URL webhook kosong.')->send();
            return;
        }

        $store = $this->sessionConfigStore();
        $existing = $store->get($this->session);
        $apiKey = trim($data['api_key'] ?? '');
        if ($apiKey === '') {
            $apiKey = $existing['apiKey'] ?? '';
        }

        $endpoint = match ($type) {
            'tracking' => '/status',
            'device_status' => '/session',
            default => '/message',
        };

        $payload = match ($type) {
            'tracking' => [
                'session' => $this->session,
                'message_id' => 'test-message-id',
                'message_status' => 'TEST',
            ],
            'device_status' => [
                'session' => $this->session,
                'status' => 'connecting',
            ],
            default => [
                'session' => $this->session,
                'from' => 'test',
                'message' => 'test webhook',
            ],
        };

        try {
            $client = Http::timeout(8)->acceptJson()->asJson();
            if ($apiKey !== '') {
                $client = $client->withHeaders(['key' => $apiKey]);
            }

            $resp = $client->post($baseUrl . $endpoint, $payload);
            $ok = $resp->successful();

            Notification::make()
                ->title($ok ? "OK ({$resp->status()})" : "GAGAL ({$resp->status()})")
                ->color($ok ? 'success' : 'danger')
                ->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('GAGAL: ' . $e->getMessage())->send();
        }
    }

    public function testSendMessage(): void
    {
        $phone = trim($this->testPhone);
        if ($phone === '') {
            Notification::make()->danger()->title('Nomor telepon wajib diisi.')->send();
            return;
        }

        try {
            $text = "Aplikasi berjalan lancar dan perangkat {$this->session} berjalan normal.";
            $this->gateway()->sendTestMessage($this->session, $phone, $text);
            Notification::make()->success()->title('Pesan tes berhasil dikirim.')->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Gagal: ' . $e->getMessage())->send();
        }
    }

    public function getGroups(): array
    {
        try {
            return $this->gateway()->listGroups($this->session);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getMessageStatuses(): array
    {
        try {
            return $this->gateway()->listMessageStatuses($this->session);
        } catch (\Throwable) {
            return [];
        }
    }

    protected function canManageSession(): bool
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return true;
        }

        return DeviceOwnership::where('user_id', $user->id)
            ->where('session_id', $this->session)
            ->exists();
    }

    private function gateway(): GatewayService
    {
        return new GatewayService(config('gateway.base_url'), config('gateway.api_key'));
    }

    private function sessionConfigStore(): SessionConfigStore
    {
        return new SessionConfigStore(config('gateway.session_config_path'));
    }
}
