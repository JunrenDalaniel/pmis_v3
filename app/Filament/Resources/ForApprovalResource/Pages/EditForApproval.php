<?php

namespace App\Filament\Resources\ForApprovalResource\Pages;

use App\Filament\Concerns\HandlesApprovalWorkflow;
use App\Filament\Resources\ForApprovalResource;
use App\Models\LoanApplication;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditForApproval extends EditRecord
{
    use HandlesApprovalWorkflow;

    protected static string $resource = ForApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve_loan')
                ->label('Approve Loan')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->modalHeading('Approve Loan Application')
                ->modalDescription('Confirm loan approval and set official release details.')
                ->modalSubmitActionLabel('Confirm & Approve')
                ->modalIcon('heroicon-o-check-circle')
                ->modalIconColor('success')
                ->visible(fn (): bool => ! in_array($this->record->approval_status, ['Approved', 'Branch Updated']) && (! auth()->user()?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist']) && auth()->user()?->role?->code !== 'mortgage_document_specialist'))
                ->form([
                    TextInput::make('loan_account_no')
                        ->label('Loan Account Number')
                        ->placeholder('e.g. LN-ACCT-2026-00123')
                        ->default(fn () => $this->record->loan_account_no)
                        ->required(),
                    DatePicker::make('date_granted')
                        ->label('Date Granted')
                        ->native(false)
                        ->default(fn () => $this->record->date_granted ?: now()->toDateString())
                        ->required(),
                    DatePicker::make('maturity_date')
                        ->label('Maturity Date')
                        ->native(false)
                        ->default(fn () => $this->record->maturity_date)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'approval_status' => 'Approved',
                        'loan_account_no' => $data['loan_account_no'],
                        'date_granted'    => $data['date_granted'],
                        'maturity_date'   => $data['maturity_date'],
                    ]);
                    HandlesApprovalWorkflow::sendApprovalNotification($this->record, auth()->user());
                    Notification::make()
                        ->title('Loan Approved Successfully')
                        ->body("Application {$this->record->application_no} has been approved.")
                        ->success()
                        ->send();
                    $this->redirect(static::getResource()::getUrl('index'));
                }),

            Actions\Action::make('tag_deficiency')
                ->label('Tag Deficiency')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->modalHeading('Tag Document Deficiency')
                ->modalDescription('Flag missing requirements or documentation discrepancies.')
                ->modalSubmitActionLabel('Save Deficiency Findings')
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->modalIconColor('warning')
                ->visible(fn (): bool => ! auth()->user()?->isBranchUser())
                ->form([
                    Select::make('sub_status')
                        ->label('Deficiency Classification')
                        ->options([
                            'internal_deficiency' => '🏢 Internal Deficiency (Branch documentation/signatures)',
                            'external_deficiency' => '🏛️ External Deficiency (ROD, LTO, BIR, PPSR, etc.)',
                        ])
                        ->default(fn () => in_array($this->record->sub_status, ['internal_deficiency', 'external_deficiency']) ? $this->record->sub_status : 'internal_deficiency')
                        ->required(),
                    Textarea::make('deficiency_notes')
                        ->label('Deficiency Findings / Requirements Needed')
                        ->placeholder('Specify missing documents, signatures, or instructions...')
                        ->default(fn () => $this->record->deficiency_notes)
                        ->rows(4)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'sub_status'       => $data['sub_status'],
                        'deficiency_notes' => $data['deficiency_notes'],
                    ]);
                    Notification::make()
                        ->title('Deficiency Findings Saved')
                        ->warning()
                        ->send();
                    $this->fillForm();
                }),

            Actions\Action::make('disapprove_loan')
                ->label('Disapprove')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->modalHeading('Confirm Disapproval')
                ->modalDescription('Reject this loan application due to unresolved issues or borrower failure to comply.')
                ->modalSubmitActionLabel('Confirm Disapproval')
                ->modalIcon('heroicon-o-x-circle')
                ->modalIconColor('danger')
                ->visible(fn (): bool => ! in_array($this->record->approval_status, ['Disapproved']) && ! auth()->user()?->isBranchUser())
                ->form([
                    Textarea::make('disapproval_reason')
                        ->label('Official Reason for Disapproval')
                        ->placeholder('State specific reasons why this application is disapproved...')
                        ->required()
                        ->rows(4),
                ])
                ->action(function (array $data) {
                    $currentNotes = $this->record->notes ? $this->record->notes . "\n\n" : '';
                    $this->record->update([
                        'approval_status' => 'Disapproved',
                        'notes'           => $currentNotes . "[DISAPPROVAL REASON - " . now()->format('F d, Y h:i A') . "]:\n" . $data['disapproval_reason'],
                    ]);
                    HandlesApprovalWorkflow::sendApprovalNotification($this->record, auth()->user());
                    Notification::make()
                        ->title('Loan Disapproved')
                        ->danger()
                        ->send();
                    $this->redirect(static::getResource()::getUrl('index'));
                }),

            Actions\DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->can('Delete:LoanApplication') && (auth()->user()?->isHeadOfficeUser() || auth()->user()?->hasRole(['super_admin', 'Super Admin']))),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
