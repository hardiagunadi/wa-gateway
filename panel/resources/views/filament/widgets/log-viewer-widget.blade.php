@php
    $logTail = $this->getLogTail();
@endphp

<x-filament-widgets::widget>
    <x-filament::section heading="Log Server" collapsible collapsed>
        @if($logTail)
            <div class="overflow-x-auto rounded-lg bg-gray-900 p-4">
                <pre class="text-xs leading-relaxed text-green-400 font-mono whitespace-pre-wrap break-all">{{ $logTail }}</pre>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada log tersedia.</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
