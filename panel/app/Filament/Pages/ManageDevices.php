<?php

namespace App\Filament\Pages;

use App\Models\DeviceOwnership;
use App\Models\User;
use App\Services\GatewayService;
use App\Services\SessionConfigStore;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class ManageDevices extends Page implements HasTable, HasForms
{
    use InteractsWithTable, InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';
    protected static ?string $title = 'Perangkat';
    protected static ?string $navigationLabel = 'Perangkat';
    protected static string|\UnitEnum|null $navigationGroup = 'Perangkat';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.pages.manage-devices';

    public static function getNavigationBadge(): ?string
    {
        try {
            $gateway = new GatewayService(config('gateway.base_url'), config('gateway.api_key'));
            $sessions = $gateway->listSessions();
            $user = auth()->user();

            if (! $user?->isAdmin()) {
                $owned = DeviceOwnership::where('user_id', $user?->id)->pluck('session_id')->all();
                $sessions = array_intersect($sessions, $owned);
            }

            $count = count($sessions);
            return $count > 0 ? (string) $count : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Jumlah perangkat';
    }

    // Wizard state
    public int $wizardStep = 1;        // 1=form, 2=pairing
    public string $createMode = 'qr';  // 'qr' atau 'code'
    public string $createName = '';
    public string $createPhone = '';
    public string $activeSessionId = '';
    public string $qrImage = '';
    public string $pairingCode = '';
    public bool $pairingCodeSent = false;

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => $this->loadDevices()->toArray())
            ->columns([
                TextColumn::make('session_id')
                    ->label('ID Session')
                    ->copyable(),
                TextColumn::make('device_name')
                    ->label('Nama Perangkat'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'connected' => 'success',
                        'connecting' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('owner_name')
                    ->label('Pemilik')
                    ->visible(fn () => auth()->user()->isAdmin()),
            ])
            ->actions([
                Action::make('configure')
                    ->label('Konfigurasi')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(fn ($record) => DeviceConfig::getUrl(['session' => $record['session_id']])),
                Action::make('start')
                    ->label('Start')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $this->startSession($record['session_id'])),
                Action::make('restart')
                    ->label('Restart')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $this->restartSession($record['session_id'])),
                Action::make('transfer')
                    ->label('Transfer')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->visible(fn () => auth()->user()->isAdmin())
                    ->form([
                        Select::make('user_id')
                            ->label('Pemilik Baru')
                            ->options(User::orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                    ])
                    ->action(fn ($record, array $data) => $this->transferOwnership($record['session_id'], $data['user_id'])),
                Action::make('delete')
                    ->label('Hapus')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Hapus Perangkat')
                    ->modalDescription('Apakah Anda yakin ingin menghapus perangkat ini? Session akan ditutup dan data konfigurasi akan dihapus.')
                    ->action(fn ($record) => $this->closeSession($record['session_id'])),
            ])
            ->emptyStateHeading('Belum ada perangkat')
            ->emptyStateDescription('Tambahkan perangkat WhatsApp baru untuk mulai.')
            ->poll('15s');
    }

    // =============================================
    // Wizard Methods
    // =============================================

    public function openCreateModal(): void
    {
        $this->resetWizard();
        $this->dispatch('open-modal', id: 'create-device');
    }

    public function closeCreateModal(): void
    {
        $this->dispatch('close-modal', id: 'create-device');
        $this->resetWizard();
    }

    public function resetWizard(): void
    {
        $this->wizardStep = 1;
        $this->createMode = 'qr';
        $this->createName = '';
        $this->createPhone = '';
        $this->activeSessionId = '';
        $this->qrImage = '';
        $this->pairingCode = '';
        $this->pairingCodeSent = false;
    }

    /**
     * Validasi input Step 1 lalu lanjut ke Step 2.
     * - Mode QR  : langsung start session + tampilkan QR
     * - Mode Code: tampilkan nomor + tombol kirim kode
     */
    public function proceedToStep2(): void
    {
        $name  = trim($this->createName);
        $phone = preg_replace('/[^0-9]/', '', trim($this->createPhone));

        if ($name === '' || $phone === '') {
            Notification::make()->danger()->title('Nama perangkat dan nomor WhatsApp wajib diisi.')->send();
            return;
        }

        // Konversi 08xxx → 628xxx
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        $this->activeSessionId = $phone;
        $this->createPhone = $phone;

        // Simpan config nama perangkat
        $this->sessionConfigStore()->put($phone, ['deviceName' => $name]);

        $this->notifyDb(
            'Perangkat Baru Ditambahkan',
            "Perangkat {$name} ({$phone}) sedang dikonfigurasi oleh " . auth()->user()->name . '.',
            'heroicon-o-device-phone-mobile',
            'info',
            $phone,
        );

        // Cleanup session lama jika ada
        $this->cleanupSession($phone);

        // Langsung mulai pairing jika mode QR
        if ($this->createMode === 'qr') {
            $this->doStartQrPairing();
        }

        $this->wizardStep = 2;
    }

    // --- QR Mode ---

    public function refreshQr(): void
    {
        $this->doStartQrPairing();
    }

    public function checkQrStatus(): void
    {
        if ($this->checkDeviceConnected()) {
            Notification::make()->success()->title('Perangkat berhasil terhubung!')->send();
            $this->notifyDb(
                'Perangkat Terhubung',
                "Perangkat {$this->createName} ({$this->activeSessionId}) berhasil terhubung via QR Code.",
                'heroicon-o-check-circle',
                'success',
                $this->activeSessionId,
            );
            $this->closeCreateModal();
        }
    }

    // --- Pairing Code Mode ---

    public function sendPairingCode(): void
    {
        try {
            $resp = $this->gateway()->createDeviceWithPairingCode($this->activeSessionId, $this->createName);
            $this->pairingCode = $resp['pairing_code'] ?? $resp['pairingCode'] ?? '';
            $this->pairingCodeSent = true;

            DeviceOwnership::updateOrCreate(
                ['session_id' => $this->activeSessionId],
                ['user_id' => auth()->id()]
            );

            Notification::make()->success()->title('Kode pairing berhasil dibuat.')->send();
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'already exist')) {
                $this->cleanupSession($this->activeSessionId);
                try {
                    $resp = $this->gateway()->createDeviceWithPairingCode($this->activeSessionId, $this->createName);
                    $this->pairingCode = $resp['pairing_code'] ?? $resp['pairingCode'] ?? '';
                    $this->pairingCodeSent = true;
                    DeviceOwnership::updateOrCreate(
                        ['session_id' => $this->activeSessionId],
                        ['user_id' => auth()->id()]
                    );
                    Notification::make()->success()->title('Kode pairing berhasil dibuat.')->send();
                } catch (\Throwable) {
                    Notification::make()->danger()->title('Gagal mengirim kode pairing.')->send();
                }
            } else {
                Notification::make()->danger()->title('Gagal: ' . $e->getMessage())->send();
            }
        }
    }

    public function resendPairingCode(): void
    {
        $this->pairingCodeSent = false;
        $this->pairingCode = '';

        $this->cleanupSession($this->activeSessionId);
        $this->sendPairingCode();
    }

    public function checkPairingStatus(): void
    {
        if ($this->checkDeviceConnected()) {
            Notification::make()->success()->title('Perangkat berhasil terhubung!')->send();
            $this->notifyDb(
                'Perangkat Terhubung',
                "Perangkat {$this->createName} ({$this->activeSessionId}) berhasil terhubung via Pairing Code.",
                'heroicon-o-check-circle',
                'success',
                $this->activeSessionId,
            );
            $this->closeCreateModal();
        }
    }

    // =============================================
    // Private Helpers
    // =============================================

    private function doStartQrPairing(): void
    {
        try {
            $response = $this->gateway()->startSession($this->activeSessionId);
            $this->qrImage = $response['qr_image'] ?? $response['qrImage'] ?? $response['qr'] ?? '';

            DeviceOwnership::updateOrCreate(
                ['session_id' => $this->activeSessionId],
                ['user_id' => auth()->id()]
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'already exist')) {
                $this->cleanupSession($this->activeSessionId);
                try {
                    $response = $this->gateway()->startSession($this->activeSessionId);
                    $this->qrImage = $response['qr_image'] ?? $response['qrImage'] ?? $response['qr'] ?? '';
                    DeviceOwnership::updateOrCreate(
                        ['session_id' => $this->activeSessionId],
                        ['user_id' => auth()->id()]
                    );
                } catch (\Throwable) {
                    Notification::make()->danger()->title('Gagal memuat QR Code.')->send();
                }
            } else {
                Notification::make()->danger()->title('Gagal: ' . $e->getMessage())->send();
            }
        }
    }

    private function cleanupSession(string $sessionId): void
    {
        try { $this->gateway()->logoutSession($sessionId); } catch (\Throwable) {}
        try { $this->gateway()->deleteSession($sessionId); } catch (\Throwable) {}
        usleep(500_000);
    }

    private function checkDeviceConnected(): bool
    {
        try {
            $statuses = collect($this->gateway()->listSessionStatuses())->keyBy('id');
            $status = $statuses->get($this->activeSessionId);
            return is_array($status) && ($status['status'] ?? '') === 'connected';
        } catch (\Throwable) {
            return false;
        }
    }

    // =============================================
    // Device Table Methods
    // =============================================

    protected function loadDevices(): Collection
    {
        try {
            $gateway = $this->gateway();
            $sessions = $gateway->listSessions();
            $statuses = collect($gateway->listSessionStatuses())->keyBy('id');
        } catch (\Throwable) {
            return collect();
        }

        $store = $this->sessionConfigStore();
        $user = auth()->user();

        if (! $user->isAdmin()) {
            $owned = DeviceOwnership::where('user_id', $user->id)->pluck('session_id')->all();
            $sessions = array_values(array_intersect($sessions, $owned));
        }

        $ownerships = DeviceOwnership::with('user:id,name')
            ->whereIn('session_id', $sessions)
            ->get()
            ->keyBy('session_id');

        return collect($sessions)->map(function ($sessionId) use ($statuses, $store, $ownerships) {
            $config = $store->get($sessionId);
            $status = $statuses->get($sessionId);
            $ownership = $ownerships->get($sessionId);

            return [
                '__key' => $sessionId,
                'session_id' => $sessionId,
                'device_name' => $config['deviceName'] ?? $sessionId,
                'status' => is_array($status) ? ($status['status'] ?? 'disconnected') : 'disconnected',
                'owner_name' => $ownership?->user?->name ?? '-',
                'owner_id' => $ownership?->user_id,
            ];
        });
    }

    protected function startSession(string $sessionId): void
    {
        if (! $this->canManageSession($sessionId)) {
            Notification::make()->danger()->title('Tidak punya akses ke session ini.')->send();
            return;
        }

        try {
            $response = $this->gateway()->startSession($sessionId);
            $qr = $response['qr_image'] ?? $response['qrImage'] ?? $response['qr'] ?? null;

            if ($qr) {
                $this->dispatch('show-qr', qr: $qr, session: $sessionId);
            }

            Notification::make()->success()->title($qr ? 'Scan QR untuk menghubungkan.' : 'Session berhasil tersambung.')->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Gagal: ' . $e->getMessage())->send();
        }
    }

    protected function restartSession(string $sessionId): void
    {
        if (! $this->canManageSession($sessionId)) {
            Notification::make()->danger()->title('Tidak punya akses ke session ini.')->send();
            return;
        }

        try {
            $response = $this->gateway()->restartSession($sessionId);
            $qr = $response['qr_image'] ?? $response['qrImage'] ?? $response['qr'] ?? null;

            if ($qr) {
                $this->dispatch('show-qr', qr: $qr, session: $sessionId);
            }

            Notification::make()->success()->title('Perangkat di-restart.')->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Gagal: ' . $e->getMessage())->send();
        }
    }

    protected function closeSession(string $sessionId): void
    {
        if (! $this->canManageSession($sessionId)) {
            Notification::make()->danger()->title('Tidak punya akses ke session ini.')->send();
            return;
        }

        // Logout dulu agar session bersih, lalu hapus — abaikan error API
        try { $this->gateway()->logoutSession($sessionId); } catch (\Throwable) {}
        try { $this->gateway()->deleteSession($sessionId); } catch (\Throwable) {}

        // Notifikasi sebelum hapus ownership (agar pemilik masih terdeteksi)
        $this->notifyDb(
            'Perangkat Dihapus',
            "Perangkat {$sessionId} telah dihapus oleh " . auth()->user()->name . '.',
            'heroicon-o-trash',
            'danger',
            $sessionId,
        );

        // Selalu bersihkan data lokal meski API gagal
        $this->sessionConfigStore()->delete($sessionId);
        DeviceOwnership::where('session_id', $sessionId)->delete();

        Notification::make()->success()->title("Device {$sessionId} dihapus.")->send();
    }

    protected function transferOwnership(string $sessionId, int $userId): void
    {
        DeviceOwnership::updateOrCreate(
            ['session_id' => $sessionId],
            ['user_id' => $userId]
        );

        Notification::make()->success()->title("Kepemilikan device {$sessionId} dipindahkan.")->send();
    }

    protected function canManageSession(string $sessionId): bool
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return true;
        }

        return DeviceOwnership::where('user_id', $user->id)
            ->where('session_id', $sessionId)
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

    private function notifyDb(string $title, string $body = '', string $icon = 'heroicon-o-bell', string $color = 'info', ?string $sessionId = null): void
    {
        $recipients = collect();

        // Admin selalu dapat semua notifikasi
        $admins = User::where('role', 'admin')->get();
        $recipients = $recipients->merge($admins);

        // Pemilik perangkat juga dapat notifikasi terkait perangkatnya
        if ($sessionId) {
            $ownerIds = DeviceOwnership::where('session_id', $sessionId)->pluck('user_id');
            $owners = User::whereIn('id', $ownerIds)->get();
            $recipients = $recipients->merge($owners);
        }

        $recipients = $recipients->unique('id');

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->icon($icon)
            ->color($color)
            ->sendToDatabase($recipients);
    }
}
