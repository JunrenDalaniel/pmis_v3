<?php

namespace App\Filament\Resources\RodStatusResource\Pages;

use App\Filament\Resources\RodStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRodStatuses extends ListRecords
{
    protected static string $resource = RodStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
