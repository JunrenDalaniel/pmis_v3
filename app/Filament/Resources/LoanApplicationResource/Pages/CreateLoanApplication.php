<?php

namespace App\Filament\Resources\LoanApplicationResource\Pages;

use App\Filament\Resources\ForApprovalResource;
use App\Filament\Resources\LoanApplicationResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateLoanApplication extends CreateRecord
{
    protected static string $resource = LoanApplicationResource::class;

    public function getTitle(): string
    {
        return 'New Mortgage Application';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
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
        return ForApprovalResource::getUrl('index');
    }
}
