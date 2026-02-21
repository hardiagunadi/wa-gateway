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
        <div class="flex items-end gap-3">
            <div class="flex-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Nomor Tujuan</label>
                <input
                    type="text"
                    wire:model="testPhone"
                    placeholder="6281234567890"
                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white sm:text-sm"
                />
            </div>
            <x-filament::button
                wire:click="testSendMessage"
                wire:loading.attr="disabled"
                type="button"
            >
                Kirim
            </x-filament::button>
        </div>
    </x-filament::section>

    {{-- Groups --}}
    <x-filament::section heading="Daftar Grup" class="mt-6" collapsible collapsed>
        @php $groups = $this->getGroups(); @endphp
        @if(count($groups) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="px-3 py-2 text-left font-medium text-gray-500">ID</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Nama</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Peserta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($groups as $group)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-3 py-2 font-mono text-xs">{{ $group['id'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $group['subject'] ?? $group['name'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $group['size'] ?? count($group['participants'] ?? []) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada grup ditemukan atau session belum terhubung.</p>
        @endif
    </x-filament::section>

    {{-- Message Statuses --}}
    <x-filament::section heading="Status Pesan" class="mt-6" collapsible collapsed>
        @php $messages = $this->getMessageStatuses(); @endphp
        @if(count($messages) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="px-3 py-2 text-left font-medium text-gray-500">ID</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Tujuan</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Status</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($messages as $msg)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-3 py-2 font-mono text-xs">{{ $msg['id'] ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $msg['to'] ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-green-100 text-green-700' => ($msg['status'] ?? '') === 'delivered' || ($msg['status'] ?? '') === 'read',
                                        'bg-yellow-100 text-yellow-700' => ($msg['status'] ?? '') === 'sent' || ($msg['status'] ?? '') === 'pending',
                                        'bg-red-100 text-red-700' => ($msg['status'] ?? '') === 'failed',
                                        'bg-gray-100 text-gray-700' => !in_array($msg['status'] ?? '', ['delivered', 'read', 'sent', 'pending', 'failed']),
                                    ])>
                                        {{ $msg['status'] ?? '-' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $msg['timestamp'] ?? $msg['created_at'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada data status pesan.</p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
