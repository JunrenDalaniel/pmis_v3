<?php

namespace App\Filament\Resources\BranchUpdateResource\Pages;

use App\Filament\Concerns\HandlesApprovalWorkflow;
use App\Filament\Resources\BranchUpdateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBranchUpdate extends EditRecord
{
    use HandlesApprovalWorkflow;

    protected static string $resource = BranchUpdateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }


    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
