<?php

namespace App\Filament\Resources\DisapprovedLoanResource\Pages;

use App\Filament\Resources\DisapprovedLoanResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDisapprovedLoans extends ListRecords
{
    protected static string $resource = DisapprovedLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function mount(): void
    {
        parent::mount();

        $stage = request()->query('stage');
        if (filled($stage)) {
            $tabMap = [
                'initial_assessment' => 'initial_assessment',
                'for_notary'         => 'for_notary',
                'notarized'          => 'notarized',
                'for_bir'            => 'for_bir',
                'bir_paid'           => 'bir_paid',
                'for_rod'            => 'for_rod',
            ];
            if (isset($tabMap[strtolower($stage)])) {
                $this->activeTab = $tabMap[strtolower($stage)];
            }
        }
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(fn () => static::getResource()::getEloquentQuery()->count() ?: null),
            'initial_assessment' => Tab::make('Initial Assessment')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['Initial Assessment']))
                ->badge(fn () => static::getResource()::getEloquentQuery()->whereIn('status', ['Initial Assessment'])->count() ?: null),
            'for_notary' => Tab::make('For Notary')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['For Notary']))
                ->badge(fn () => static::getResource()::getEloquentQuery()->whereIn('status', ['For Notary'])->count() ?: null),
            'notarized' => Tab::make('Notarized')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['Notarized']))
                ->badge(fn () => static::getResource()::getEloquentQuery()->whereIn('status', ['Notarized'])->count() ?: null),
            'for_bir' => Tab::make('For BIR')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['For BIR', 'For Bir']))
                ->badge(fn () => static::getResource()::getEloquentQuery()->whereIn('status', ['For BIR', 'For Bir'])->count() ?: null),
            'bir_paid' => Tab::make('BIR Paid')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['BIR Paid', 'Bir Paid']))
                ->badge(fn () => static::getResource()::getEloquentQuery()->whereIn('status', ['BIR Paid', 'Bir Paid'])->count() ?: null),
            'for_rod' => Tab::make('For ROD')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['For ROD', 'For Rod']))
                ->badge(fn () => static::getResource()::getEloquentQuery()->whereIn('status', ['For ROD', 'For Rod'])->count() ?: null),
        ];
    }
}

