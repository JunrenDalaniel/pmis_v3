<?php

namespace App\Filament\Resources\RodBranchResource\Pages;

use App\Filament\Resources\RodBranchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRodBranches extends ListRecords
{
    protected static string $resource = RodBranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
