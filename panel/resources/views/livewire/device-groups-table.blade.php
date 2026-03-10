<div class="space-y-3">
    <div class="fi-input-wrp flex rounded-lg shadow-sm ring-1 ring-gray-950/10 dark:ring-white/20 overflow-hidden">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Cari grup..."
            class="fi-input block w-full border-none bg-transparent py-1.5 px-3 text-sm text-gray-950 outline-none transition duration-75 placeholder:text-gray-400 focus:ring-0 dark:text-white dark:placeholder:text-gray-500"
        />
    </div>
    {{ $this->table }}
</div>
