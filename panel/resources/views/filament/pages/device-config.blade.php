<x-filament-panels::page>
    <form wire:submit="saveConfig">
        {{ $this->configForm }}

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <x-filament::button type="submit">
                Simpan Konfigurasi
            </x-filament::button>

            @if($this->configData['incoming_enabled'] ?? false)
            <x-filament::button
                color="gray"
                wire:click="testWebhook('incoming')"
                wire:loading.attr="disabled"
                type="button"
            >
                Test Webhook Pesan
            </x-filament::button>
            @endif

            @if($this->configData['tracking_enabled'] ?? false)
            <x-filament::button
                color="gray"
                wire:click="testWebhook('tracking')"
                wire:loading.attr="disabled"
                type="button"
            >
                Test Webhook Tracking
            </x-filament::button>
            @endif

            @if($this->configData['device_status_enabled'] ?? false)
            <x-filament::button
                color="gray"
                wire:click="testWebhook('device_status')"
                wire:loading.attr="disabled"
                type="button"
            >
                Test Webhook Status
            </x-filament::button>
            @endif
        </div>
    </form>

    {{-- Test Send Message --}}
    <x-filament::section heading="Kirim Pesan Tes" class="mt-6" collapsible collapsed>
        {{ $this->testSendForm }}

        <div class="mt-4">
            <x-filament::button
                wire:click="testSendMessage"
                wire:loading.attr="disabled"
                type="button"
                icon="heroicon-o-paper-airplane"
            >
                <span wire:loading.remove wire:target="testSendMessage">Kirim Pesan</span>
                <span wire:loading wire:target="testSendMessage">Mengirim...</span>
            </x-filament::button>
        </div>
    </x-filament::section>

    {{-- Groups --}}
    <x-filament::section heading="Daftar Grup" class="mt-6" collapsible collapsed>
        @livewire(\App\Livewire\DeviceGroupsTable::class, ['session' => $this->session], key('groups-'.$this->session))
    </x-filament::section>

    {{-- Message Statuses --}}
    <x-filament::section heading="Status Pesan" class="mt-6" collapsible collapsed>
        @livewire(\App\Livewire\DeviceMessagesTable::class, ['session' => $this->session], key('messages-'.$this->session))
    </x-filament::section>
</x-filament-panels::page>
