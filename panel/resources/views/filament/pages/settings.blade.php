<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="saveGateway">
            {{ $this->gatewayForm }}

            <div class="mt-4">
                <x-filament::button type="submit">
                    Simpan Konfigurasi Gateway
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    <x-filament::section>
        <form wire:submit="saveResetSessions">
            {{ $this->resetForm }}

            <div class="mt-4">
                <x-filament::button type="submit">
                    Simpan Device Reset Password
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
