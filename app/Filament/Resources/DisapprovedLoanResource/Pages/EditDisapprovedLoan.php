<?php

namespace App\Filament\Resources\DisapprovedLoanResource\Pages;

use App\Filament\Concerns\HandlesApprovalWorkflow;
use App\Filament\Resources\DisapprovedLoanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDisapprovedLoan extends EditRecord
{
    use HandlesApprovalWorkflow;

    protected static string $resource = DisapprovedLoanResource::class;

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
