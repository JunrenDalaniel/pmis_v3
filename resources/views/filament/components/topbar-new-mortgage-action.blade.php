@php
    $canCreate = \App\Filament\Resources\LoanApplicationResource::canCreate();
@endphp

@if ($canCreate)
    <div class="flex items-center me-3">
        <x-filament::button
            tag="a"
            href="{{ \App\Filament\Resources\LoanApplicationResource::getUrl('create') }}"
            icon="heroicon-m-plus"
            size="sm"
            color="primary"
            class="shadow-sm font-semibold whitespace-nowrap"
        >
            New Mortgage Application
        </x-filament::button>
    </div>
@endif
