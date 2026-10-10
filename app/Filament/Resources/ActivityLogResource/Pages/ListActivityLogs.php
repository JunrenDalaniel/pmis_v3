<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\ActivityLogResource\Widgets\AuditLogStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
