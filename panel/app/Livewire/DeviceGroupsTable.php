<?php

namespace App\Livewire;

use App\Services\GatewayService;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class DeviceGroupsTable extends Component implements HasTable, HasForms, HasActions
{
    use InteractsWithTable, InteractsWithForms, InteractsWithActions;

    public string $session = '';
    public string $search = '';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => $this->loadGroups())
            ->columns([
                TextColumn::make('id')
                    ->label('ID Grup')
                    ->size('xs')
                    ->fontFamily('mono')
                    ->wrap(),
                TextColumn::make('name')
                    ->label('Nama Grup')
                    ->weight('medium'),
                TextColumn::make('size')
                    ->label('Peserta')
                    ->alignCenter()
                    ->badge()
                    ->color('primary'),
            ])
            ->actions([
                Action::make('copy_id')
                    ->label('Salin ID')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('gray')
                    ->size('xs')
                    ->action(function (array $record) {
                        $this->js("window.navigator.clipboard.writeText('" . addslashes($record['id']) . "')");
                        Notification::make()
                            ->success()
                            ->title('ID Grup disalin!')
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Tidak ada grup')
            ->emptyStateDescription('Tidak ada grup ditemukan atau session belum terhubung.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    protected function loadGroups(): array
    {
        try {
            $gateway = new GatewayService(config('gateway.base_url'), config('gateway.api_key'));
            $groups = $gateway->listGroups($this->session);
            $search = strtolower(trim($this->search));

            return collect($groups)->map(fn ($g, $i) => [
                '__key' => $g['id'] ?? (string) $i,
                'id' => $g['id'] ?? '-',
                'name' => $g['subject'] ?? $g['name'] ?? '-',
                'size' => $g['size'] ?? count($g['participants'] ?? []),
            ])->when($search !== '', fn ($col) => $col->filter(
                fn ($r) => str_contains(strtolower($r['id']), $search)
                    || str_contains(strtolower($r['name']), $search)
            ))->values()->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    public function render()
    {
        return view('livewire.device-groups-table');
    }
}
