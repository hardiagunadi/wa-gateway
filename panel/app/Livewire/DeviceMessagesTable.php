<?php

namespace App\Livewire;

use App\Services\GatewayService;
use Carbon\Carbon;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class DeviceMessagesTable extends Component implements HasTable, HasForms, HasActions
{
    use InteractsWithTable, InteractsWithForms, InteractsWithActions;

    public string $session = '';
    public string $search = '';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => $this->loadMessages())
            ->columns([
                TextColumn::make('from')
                    ->label('Dari')
                    ->size('xs')
                    ->color('gray'),
                TextColumn::make('to')
                    ->label('Tujuan')
                    ->size('xs')
                    ->color('gray'),
                TextColumn::make('preview')
                    ->label('Isi Pesan')
                    ->size('xs')
                    ->color('gray')
                    ->wrap()
                    ->limit(80)
                    ->placeholder('—'),
                TextColumn::make('category')
                    ->label('Tipe')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'text' => 'Teks',
                        'image' => 'Gambar',
                        'video' => 'Video',
                        'audio' => 'Audio',
                        'document' => 'Dokumen',
                        'sticker' => 'Stiker',
                        'location' => 'Lokasi',
                        'contact' => 'Kontak',
                        'unknown' => 'Lainnya',
                        default => $state,
                    })
                    ->alignCenter(),
                TextColumn::make('direction')
                    ->label('Arah')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Masuk' => 'info',
                        'Keluar' => 'primary',
                        default => 'gray',
                    })
                    ->alignCenter(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'delivered', 'read', 'received' => 'success',
                        'sent', 'pending' => 'warning',
                        'failed', 'error' => 'danger',
                        default => 'gray',
                    })
                    ->alignCenter(),
                TextColumn::make('time')
                    ->label('Waktu')
                    ->size('xs')
                    ->alignEnd()
                    ->color('gray'),
            ])
            ->emptyStateHeading('Tidak ada data')
            ->emptyStateDescription('Tidak ada data status pesan. Data hanya tersedia sejak server gateway terakhir restart (in-memory storage).')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->striped()
            ->paginated([10, 25, 50, 'all']);
    }

    protected function loadMessages(): array
    {
        try {
            $gateway = new GatewayService(config('gateway.base_url'), config('gateway.api_key'));
            $messages = $gateway->listMessageStatuses($this->session);
            $search = strtolower(trim($this->search));

            return collect($messages)
                ->filter(fn ($m) => ! str_starts_with(($m['to'] ?? ''), 'status'))
                ->map(fn ($m, $i) => [
                    '__key' => $m['id'] ?? (string) $i,
                    'from' => $this->formatNumber($m['from'] ?? '-'),
                    'to' => $this->formatNumber($m['to'] ?? '-'),
                    'preview' => $m['preview'] ?? null,
                    'category' => $m['category'] ?? '-',
                    'direction' => match ($m['direction'] ?? '-') {
                        'incoming' => 'Masuk',
                        'outgoing' => 'Keluar',
                        default => $m['direction'] ?? '-',
                    },
                    'status' => $m['status'] ?? '-',
                    'time' => $this->formatTime($m['updatedAt'] ?? $m['createdAt'] ?? null),
                ])
                ->when($search !== '', fn ($col) => $col->filter(
                    fn ($r) => str_contains(strtolower($r['to']), $search)
                        || str_contains(strtolower($r['from']), $search)
                        || str_contains(strtolower($r['preview'] ?? ''), $search)
                        || str_contains(strtolower($r['status']), $search)
                ))
                ->values()
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function formatNumber(string $number): string
    {
        return preg_replace('/@(s\.whatsapp\.net|g\.us)$/', '', $number);
    }

    protected function formatTime(?string $timestamp): string
    {
        if (! $timestamp) {
            return '-';
        }

        try {
            return Carbon::parse($timestamp)->timezone('Asia/Jakarta')->format('d M Y H:i');
        } catch (\Throwable) {
            return $timestamp;
        }
    }

    public function render()
    {
        return view('livewire.device-messages-table');
    }
}
