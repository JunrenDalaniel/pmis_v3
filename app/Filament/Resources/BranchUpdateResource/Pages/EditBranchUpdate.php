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


    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            Actions\Action::make('saveAndClose')
                ->label('Save & Close')
                ->color('gray')
                ->action('saveAndClose'),
            $this->getCancelFormAction(),
        ];
    }

    public function saveAndClose(): void
    {
        $this->save(shouldRedirect: false);

        $url = match ($this->record->approval_status) {
            'Pending Approval' => \App\Filament\Resources\ForApprovalResource::getUrl('index'),
            'Disapproved'      => \App\Filament\Resources\DisapprovedLoanResource::getUrl('index'),
            'Approved'         => \App\Filament\Resources\LoanApplicationResource::getUrl('index'),
            default            => static::getResource()::getUrl('index'),
        };

        $this->redirect($url);
    }

    protected function getRedirectUrl(): ?string
    {
        return match ($this->record->approval_status) {
            'Pending Approval' => \App\Filament\Resources\ForApprovalResource::getUrl('index'),
            'Disapproved'      => \App\Filament\Resources\DisapprovedLoanResource::getUrl('index'),
            'Approved'         => \App\Filament\Resources\LoanApplicationResource::getUrl('index'),
            default            => null,
        };
    }
}
