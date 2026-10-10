<?php

namespace App\Filament\Resources\ForApprovalResource\Pages;

use App\Filament\Resources\ForApprovalResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListForApprovals extends ListRecords
{
    protected static string $resource = ForApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [];
    }
}
