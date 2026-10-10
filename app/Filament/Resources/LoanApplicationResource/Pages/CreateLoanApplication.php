<?php

namespace App\Filament\Resources\LoanApplicationResource\Pages;

use App\Filament\Resources\ForApprovalResource;
use App\Filament\Resources\LoanApplicationResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;

class CreateLoanApplication extends CreateRecord
{
    use HasWizard;

    protected static string $resource = LoanApplicationResource::class;

    public function getTitle(): string
    {
        return 'New Mortgage Application';
    }

    public function getSteps(): array
    {
        return LoanApplicationResource::getCreateWizardSteps();
    }

    protected function hasSkippableSteps(): bool
    {
        return false;
    }

    protected function getSubmitFormAction(): Action
    {
        return parent::getSubmitFormAction()
            ->label('Create Application')
            ->icon('heroicon-o-check-circle');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $year = date('Y');
        $nextId = (\App\Models\LoanApplication::max('id') ?? 0) + 1;
        $appNo = $data['application_no'] ?? ('API-LA-' . $year . '-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT));
        while (\App\Models\LoanApplication::where('application_no', $appNo)->exists()) {
            $nextId++;
            $appNo = 'API-LA-' . $year . '-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
        }
        $data['application_no'] = $appNo;

        $data['approval_status'] = 'Pending Approval';
        $data['sub_status'] = 'none';
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('⏳ Loan Application Submitted')
            ->body('New loan application has been submitted and routed to the For Approval queue for evaluation.')
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return ForApprovalResource::getUrl('edit', ['record' => $this->record]);
    }
}
