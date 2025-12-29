<x-filament-panels::page>
    <div wire:poll.{{ $this->getPollingInterval() }}>
        {{ $this->infolist }}

        <div class="mt-6">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
