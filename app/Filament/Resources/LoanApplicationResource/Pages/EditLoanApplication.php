<?php

namespace App\Filament\Resources\LoanApplicationResource\Pages;

use App\Filament\Concerns\HandlesApprovalWorkflow;
use App\Filament\Resources\LoanApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLoanApplication extends EditRecord
{
    use HandlesApprovalWorkflow;

    protected static string $resource = LoanApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\LoanProgressStepperWidget::class,
        ];
    }

    protected function getHeaderWidgetsData(): array
    {
        return [
            'record' => $this->record,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return match ($this->record->approval_status) {
            'Pending Approval' => \App\Filament\Resources\ForApprovalResource::getUrl('index'),
            'Disapproved'      => \App\Filament\Resources\DisapprovedLoanResource::getUrl('index'),
            'Branch Updated'   => \App\Filament\Resources\BranchUpdateResource::getUrl('index'),
            default            => static::getResource()::getUrl('index'),
        };
    }
}
