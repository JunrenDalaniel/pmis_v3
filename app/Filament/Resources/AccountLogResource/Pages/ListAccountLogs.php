<?php

namespace App\Filament\Resources\AccountLogResource\Pages;

use App\Filament\Resources\AccountLogResource;
use App\Filament\Resources\AccountLogResource\Widgets\AccountLogStatsWidget;
use Filament\Resources\Pages\ListRecords;

class ListAccountLogs extends ListRecords
{
    protected static string $resource = AccountLogResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            AccountLogStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
