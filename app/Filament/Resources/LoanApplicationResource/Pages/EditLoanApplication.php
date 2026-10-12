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

    public function mount(int | string $record): void
    {
        parent::mount($record);

        if ($this->record->approval_status === 'Pending Approval') {
            $this->redirect(\App\Filament\Resources\ForApprovalResource::getUrl('edit', ['record' => $this->record]));
        } elseif ($this->record->approval_status === 'Disapproved') {
            $this->redirect(\App\Filament\Resources\DisapprovedLoanResource::getUrl('edit', ['record' => $this->record]));
        }
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
