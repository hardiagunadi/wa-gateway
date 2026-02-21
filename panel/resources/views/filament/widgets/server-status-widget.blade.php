@php
    $status = $this->getServerStatus();
    $running = $status['running'] ?? false;
    $isAdmin = auth()->user()?->isAdmin();
@endphp

<x-filament-widgets::widget>
    <x-filament::section heading="WA Gateway Server (PM2)">
        <div class="space-y-3">
            <div class="flex items-center gap-2">
                <span @class([
                    'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                    'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' => $running,
                    'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300' => ! $running,
                ])>
                    {{ $running ? 'Online' : 'Offline' }}
                </span>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $status['pm2Status'] ?? 'unknown' }}
                </span>
            </div>

            @if($running)
                <div class="grid grid-cols-2 gap-2 text-sm">
                    @if($status['pid'])
                        <div class="text-gray-500 dark:text-gray-400">PID</div>
                        <div class="font-medium">{{ $status['pid'] }}</div>
                    @endif
                    @if($status['uptime'])
                        <div class="text-gray-500 dark:text-gray-400">Uptime</div>
                        <div class="font-medium">{{ $status['uptime'] }}</div>
                    @endif
                    @if($status['memory'])
                        <div class="text-gray-500 dark:text-gray-400">Memory</div>
                        <div class="font-medium">{{ $status['memory'] }}</div>
                    @endif
                    @if($status['cpu'])
                        <div class="text-gray-500 dark:text-gray-400">CPU</div>
                        <div class="font-medium">{{ $status['cpu'] }}</div>
                    @endif
                    @if(($status['restarts'] ?? 0) > 0)
                        <div class="text-gray-500 dark:text-gray-400">Restarts</div>
                        <div class="font-medium">{{ $status['restarts'] }}</div>
                    @endif
                </div>
            @endif

            @if($isAdmin)
                <div class="flex gap-2 pt-2">
                    @if(! $running)
                        <x-filament::button
                            color="success"
                            size="sm"
                            wire:click="startServer"
                            wire:loading.attr="disabled"
                        >
                            Start
                        </x-filament::button>
                    @else
                        <x-filament::button
                            color="warning"
                            size="sm"
                            wire:click="restartServer"
                            wire:loading.attr="disabled"
                        >
                            Restart
                        </x-filament::button>
                        <x-filament::button
                            color="danger"
                            size="sm"
                            wire:click="stopServer"
                            wire:loading.attr="disabled"
                        >
                            Stop
                        </x-filament::button>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
