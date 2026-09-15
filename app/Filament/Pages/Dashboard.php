<?php

namespace App\Filament\Pages;

use App\Filament\Resources\LoanApplicationResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('new_mortgage_application')
                ->label('New Mortgage Application')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->url(fn (): string => LoanApplicationResource::getUrl('create'))
                ->visible(fn (): bool => LoanApplicationResource::canCreate()),
        ];
    }
}
