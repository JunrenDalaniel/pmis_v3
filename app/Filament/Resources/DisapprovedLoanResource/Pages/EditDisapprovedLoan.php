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
            Actions\Action::make('resubmit_for_evaluation')
                ->label('Resubmit for Evaluation')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->record->approval_status === 'Disapproved')
                ->modalHeading(fn (): string => "Resubmit Loan: {$this->record->application_no}")
                ->modalDescription('Submit this loan application back to Head Office for re-evaluation along with compliance or deficiency remarks.')
                ->modalSubmitActionLabel('Confirm & Resubmit')
                ->modalIcon('heroicon-o-arrow-path')
                ->modalIconColor('warning')
                ->form([
                    \Filament\Forms\Components\Textarea::make('resubmission_notes')
                        ->label('Comment / Resubmission Reason')
                        ->placeholder('Specify the corrections made (e.g. Uploaded corrected PSA certificate, updated CTC of Title)...')
                        ->required()
                        ->rows(5),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'approval_status' => 'Pending Approval',
                        'sub_status'      => 'none',
                    ]);

                    HandlesApprovalWorkflow::sendApprovalNotification($this->record, auth()->user());

                    if (filled($data['resubmission_notes'] ?? null)) {
                        $this->record->applicationNotes()->create([
                            'user_id' => auth()->id() ?? $this->record->created_by_user_id,
                            'stage'   => ($this->record->status ?? 'Initial Assessment') . ' (Branch Resubmission Comment)',
                            'note'    => $data['resubmission_notes'],
                        ]);
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('⏳ Loan Resubmitted for Evaluation')
                        ->body("Application {$this->record->application_no} for " . ($this->record->member?->full_name ?? 'Borrower') . " has been returned to the Pending Approval queue.")
                        ->warning()
                        ->send();

                    $this->redirect(\App\Filament\Resources\ForApprovalResource::getUrl('index'));
                }),
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

        $nonReleasedStages = [
            'Initial Assessment',
            'For Notary',
            'Notarized',
            'For BIR', 'For Bir',
            'BIR Paid', 'Bir Paid',
            'For ROD', 'For Rod',
        ];

        $url = match ($this->record->approval_status) {
            'Pending Approval' => \App\Filament\Resources\ForApprovalResource::getUrl('index'),
            'Branch Updated'   => \App\Filament\Resources\BranchUpdateResource::getUrl('index'),
            'Approved'         => !in_array($this->record->status, $nonReleasedStages)
                ? \App\Filament\Resources\LoanApplicationResource::getUrl('index')
                : \App\Filament\Resources\DisapprovedLoanResource::getUrl('index'),
            default            => \App\Filament\Resources\DisapprovedLoanResource::getUrl('index'),
        };

        $this->redirect($url);
    }

    protected function getRedirectUrl(): ?string
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
            default            => null,
        };
    }
}
