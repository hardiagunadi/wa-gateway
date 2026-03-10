@php
    $health = $this->getHealthStatus();
    $online = $health['online'] ?? false;
    $apiKey = config('gateway.api_key');
@endphp

<x-filament-widgets::widget>
    <x-filament::section heading="API Gateway">
        <div class="space-y-3">
            <div class="flex items-center gap-3">
                <span @class([
                    'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
                    'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' => $online,
                    'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300' => ! $online,
                ])>
                    {{ $online ? 'Online' : 'Offline' }}
                </span>
            </div>

            <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 px-3 py-2">
                <div class="text-[10px] uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-semibold mb-1">Header API Key</div>
                @if($apiKey)
                    <div class="flex items-center gap-2">
                        <code class="font-mono text-sm text-emerald-800 dark:text-emerald-200">{{ Str::mask($apiKey, '*', 4) }}</code>
                        <button
                            type="button"
                            wire:click="copyApiKey"
                            class="rounded p-0.5 text-emerald-500 hover:text-emerald-700 hover:bg-emerald-100 dark:hover:bg-emerald-800 transition"
                            title="Copy API Key"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                            </svg>
                        </button>
                    </div>
                @else
                    <span class="text-sm text-yellow-700 dark:text-yellow-300">Belum diset</span>
                @endif
            </div>

            @if($online && is_array($health['data'] ?? null))
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    @foreach($health['data'] as $key => $value)
                        @if(is_string($value) || is_numeric($value))
                            <span class="mr-3">{{ $key }}: <strong>{{ $value }}</strong></span>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
