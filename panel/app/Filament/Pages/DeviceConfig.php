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
use Illuminate\Support\Str;

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
    public ?array $testSendData = [];

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
            'header_key' => config('gateway.api_key') ?: '(belum diset)',
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
            'testSendForm',
        ];
    }

    public function configForm(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Informasi Perangkat')
                    ->schema([
                        TextInput::make('device_name')
                            ->label('Nama Perangkat')
                            ->columnSpanFull(),
                        TextInput::make('header_key')
                            ->label('Header Key')
                            ->dehydrated(false)
                            ->readOnly()
                            ->copyable(copyMessage: 'Header Key disalin!'),
                        TextInput::make('api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable()
                            ->copyable(copyMessage: 'API Key disalin!')
                            ->suffixAction(
                                \Filament\Actions\Action::make('generateApiKey')
                                    ->icon('heroicon-o-arrow-path')
                                    ->tooltip('Generate API Key')
                                    ->requiresConfirmation()
                                    ->modalHeading('Generate API Key Baru?')
                                    ->modalDescription('API Key lama akan diganti. Pastikan untuk memperbarui konfigurasi klien Anda.')
                                    ->action(function () {
                                        $newKey = Str::random(32);
                                        $this->configData['api_key'] = $newKey;
                                        Notification::make()->success()->title('API Key baru di-generate. Jangan lupa simpan.')->send();
                                    }),
                            ),
                    ])
                    ->columns(2),

                Section::make('Webhook')
                    ->schema([
                        Toggle::make('incoming_enabled')
                            ->label('Aktifkan Webhook Pesan Masuk')
                            ->live(),
                        TextInput::make('webhook_base_url')
                            ->label('Webhook Pesan Masuk')
                            ->url()
                            ->placeholder('https://example.com/webhook')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('incoming_enabled')),
                        Toggle::make('auto_reply_enabled')
                            ->label('Aktifkan Auto Reply'),
                        Toggle::make('tracking_enabled')
                            ->label('Aktifkan Tracking')
                            ->live(),
                        TextInput::make('tracking_webhook_base_url')
                            ->label('Webhook Tracking')
                            ->url()
                            ->placeholder('https://example.com/tracking')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('tracking_enabled')),
                        Toggle::make('device_status_enabled')
                            ->label('Aktifkan Webhook Status Device')
                            ->live(),
                        TextInput::make('device_status_webhook_base_url')
                            ->label('Webhook Status Device')
                            ->url()
                            ->placeholder('https://example.com/device-status')
                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('device_status_enabled')),
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

    public function testSendForm(Schema $form): Schema
    {
        return $form
            ->schema([
                TextInput::make('phone')
                    ->label('Nomor Tujuan')
                    ->placeholder('6281234567890')
                    ->required()
                    ->tel()
                    ->helperText('Bisa 08xxx atau 628xxx (tanpa spasi).'),
                \Filament\Forms\Components\Textarea::make('message')
                    ->label('Isi Pesan')
                    ->placeholder('Kosongkan untuk menggunakan pesan default...')
                    ->rows(3),
            ])
            ->statePath('testSendData');
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

        $webhookBaseUrl = $this->normalizeWebhookBaseUrl($data['webhook_base_url'] ?? null);
        $trackingBaseUrl = $this->normalizeWebhookBaseUrl($data['tracking_webhook_base_url'] ?? null)
            ?: $this->normalizeWebhookBaseUrl($existing['trackingWebhookBaseUrl'] ?? null);
        $deviceStatusBaseUrl = $this->normalizeWebhookBaseUrl($data['device_status_webhook_base_url'] ?? null)
            ?: $this->normalizeWebhookBaseUrl($existing['deviceStatusWebhookBaseUrl'] ?? null);

        $config = [
            'deviceName' => $deviceName,
            'webhookBaseUrl' => $webhookBaseUrl,
            'trackingWebhookBaseUrl' => $trackingBaseUrl,
            'deviceStatusWebhookBaseUrl' => $deviceStatusBaseUrl,
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

        $baseUrl = $this->normalizeWebhookBaseUrl($data[$urlField] ?? null) ?? '';
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

            if ($ok) {
                Notification::make()
                    ->title("OK ({$resp->status()})")
                    ->color('success')
                    ->send();
                return;
            }

            $errorDetail = trim((string) $resp->body());
            if ($errorDetail === '') {
                $errorDetail = 'Webhook merespons gagal tanpa detail body.';
            }

            Notification::make()
                ->danger()
                ->title("GAGAL ({$resp->status()})")
                ->body($errorDetail)
                ->persistent()
                ->send();
        } catch (\Throwable $e) {
            $errorDetail = trim($e->getMessage());
            if ($errorDetail === '') {
                $errorDetail = 'Terjadi error saat mengirim test webhook.';
            }

            Notification::make()
                ->danger()
                ->title('Gagal mengirim test webhook.')
                ->body($errorDetail)
                ->persistent()
                ->send();
        }
    }

    public function testSendMessage(): void
    {
        $data = $this->testSendForm->getState();

        $phone = $this->normalizePhoneForGateway($data['phone'] ?? null);
        if (! $phone) {
            Notification::make()->danger()->title('Format nomor tidak valid. Gunakan 08xxx atau 628xxx.')->send();
            return;
        }

        $text = trim($data['message'] ?? '');
        if ($text === '') {
            $text = "Aplikasi berjalan lancar dan perangkat {$this->session} berjalan normal.";
        }

        try {
            $this->gateway()->sendTestMessage($this->session, $phone, $text);
            Notification::make()->success()->title('Pesan tes berhasil dikirim.')->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Gagal: ' . $e->getMessage())->send();
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

    private function normalizeWebhookBaseUrl(?string $url): ?string
    {
        $base = rtrim(trim((string) $url), '/');
        if ($base === '') {
            return null;
        }

        $normalized = preg_replace('#/(message|auto-reply|status|session)$#i', '', $base);
        $normalized = is_string($normalized) ? rtrim($normalized, '/') : $base;

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizePhoneForGateway(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', trim($value));
        if (! is_string($digits) || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62' . $digits;
        }

        if (! str_starts_with($digits, '62')) {
            return null;
        }

        if (strlen($digits) < 10 || strlen($digits) > 16) {
            return null;
        }

        return $digits;
    }
}
