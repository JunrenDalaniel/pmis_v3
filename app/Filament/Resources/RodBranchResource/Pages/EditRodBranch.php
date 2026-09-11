<?php

namespace App\Filament\Resources\RodBranchResource\Pages;

use App\Filament\Resources\RodBranchResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRodBranch extends EditRecord
{
    protected static string $resource = RodBranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
