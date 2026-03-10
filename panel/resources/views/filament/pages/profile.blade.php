<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="saveProfile">
            {{ $this->profileForm }}

            <div class="mt-4">
                <x-filament::button type="submit">
                    Simpan Profil
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    <x-filament::section>
        <form wire:submit="savePassword">
            {{ $this->passwordForm }}

            <div class="mt-4">
                <x-filament::button type="submit" color="warning">
                    Ubah Password
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
