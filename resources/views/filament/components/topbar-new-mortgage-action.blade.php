@php
    $canCreate = \App\Filament\Resources\LoanApplicationResource::canCreate();
@endphp

@if ($canCreate)
    <div class="flex items-center ms-1 me-1 sm:ms-3 sm:me-2">
        <style>
            .topbar-btn-desktop {
                display: none !important;
            }
            .topbar-btn-mobile {
                display: inline !important;
            }
            @media (min-width: 640px) {
                .topbar-btn-desktop {
                    display: inline !important;
                }
                .topbar-btn-mobile {
                    display: none !important;
                }
            }
        </style>
        <x-filament::button
            tag="a"
            href="{{ \App\Filament\Resources\LoanApplicationResource::getUrl('create') }}"
            icon="heroicon-m-plus"
            size="sm"
            color="primary"
            class="shadow-sm font-semibold whitespace-nowrap"
            title="New Mortgage Application"
        >
            <span class="topbar-btn-desktop">New Mortgage Application</span>
            <span class="topbar-btn-mobile">New</span>
        </x-filament::button>
    </div>
@endif
