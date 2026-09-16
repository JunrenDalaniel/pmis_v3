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
        $nonReleasedStages = [
            'Initial Assessment',
            'For Notary',
            'Notarized',
            'For BIR', 'For Bir',
            'BIR Paid', 'Bir Paid',
            'For ROD', 'For Rod',
        ];

        return match ($this->record->approval_status) {
            'Pending Approval' => \App\Filament\Resources\ForApprovalResource::getUrl('index'),
            'Branch Updated'   => \App\Filament\Resources\BranchUpdateResource::getUrl('index'),
            'Approved'         => !in_array($this->record->status, $nonReleasedStages)
                ? \App\Filament\Resources\LoanApplicationResource::getUrl('index')
                : \App\Filament\Resources\DisapprovedLoanResource::getUrl('index'),
            default            => \App\Filament\Resources\DisapprovedLoanResource::getUrl('index'),
        };
    }
}
