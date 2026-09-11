<?php

namespace App\Filament\Resources\BranchUpdateResource\Pages;

use App\Filament\Resources\BranchUpdateResource;
use Filament\Resources\Pages\ListRecords;

class ListBranchUpdates extends ListRecords
{
    protected static string $resource = BranchUpdateResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
