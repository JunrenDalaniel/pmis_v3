<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap items-center justify-end gap-3 pt-6 border-t border-gray-200 dark:border-gray-800">
            <x-filament::button
                wire:click="printTransmittal"
                wire:loading.attr="disabled"
                wire:target="printTransmittal"
                icon="heroicon-o-printer"
                color="success"
                size="lg"
                class="font-semibold shadow-sm"
            >
                Print Transmittal Slip
            </x-filament::button>

            <x-filament::button
                wire:click="exportExcel"
                wire:loading.attr="disabled"
                wire:target="exportExcel"
                icon="heroicon-o-arrow-down-tray"
                color="primary"
                size="lg"
                class="font-semibold shadow-sm"
            >
                Export Official Excel (.xlsx)
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>