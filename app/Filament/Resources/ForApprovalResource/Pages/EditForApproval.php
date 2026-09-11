<?php

namespace App\Filament\Resources\ForApprovalResource\Pages;

use App\Filament\Concerns\HandlesApprovalWorkflow;
use App\Filament\Resources\ForApprovalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditForApproval extends EditRecord
{
    use HandlesApprovalWorkflow;

    protected static string $resource = ForApprovalResource::class;

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
