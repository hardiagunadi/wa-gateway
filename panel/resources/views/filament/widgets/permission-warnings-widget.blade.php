@php
    $warnings = $this->getWarnings();
@endphp

<x-filament-widgets::widget>
    @if(count($warnings) > 0)
        <x-filament::section>
            <div class="space-y-2">
                @foreach($warnings as $warning)
                    <div class="flex items-start gap-2 rounded-lg bg-yellow-50 p-3 text-sm text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300">
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
                        <span>{{ $warning }}</span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
