<?php

namespace App\Filament\Resources\ForApprovalResource\Pages;

use App\Filament\Concerns\HandlesApprovalWorkflow;
use App\Filament\Resources\ForApprovalResource;
use App\Models\LoanApplication;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;

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
                ->modalHeading(fn (): string => "Approve Loan: {$this->record->application_no}")
                ->modalDescription('Confirm loan approval and set the granted loan account details.')
                ->modalSubmitActionLabel('Confirm Approval')
                ->modalIcon('heroicon-o-check-circle')
                ->modalIconColor('success')
                ->visible(fn (): bool => ! in_array($this->record->approval_status, ['Approved', 'Branch Updated']) && (! auth()->user()?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist']) && auth()->user()?->role?->code !== 'mortgage_document_specialist'))
                ->form([
                    Grid::make(3)->schema([
                        TextInput::make('loan_account_no')
                            ->label('Loan Account Number')
                            ->placeholder('e.g. LN-ACCT-2026-00123')
                            ->default(fn () => $this->record->loan_account_no)
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('date_granted')
                            ->label('Date Granted')
                            ->native(false)
                            ->displayFormat('F d, Y')
                            ->default(fn () => $this->record->date_granted ?? now())
                            ->live()
                            ->maxDate(now()->addYear())
                            ->required(),
                        DatePicker::make('maturity_date')
                            ->label('Maturity Date')
                            ->native(false)
                            ->displayFormat('F d, Y')
                            ->default(fn () => $this->record->maturity_date)
                            ->minDate(fn ($get) => $get('date_granted') ?: null)
                            ->maxDate(now()->addYears(15))
                            ->afterOrEqual('date_granted'),
                    ]),
                    Textarea::make('approval_notes')
                        ->label('Comment / Approval Reason (Optional)')
                        ->placeholder('Enter any evaluation comments or approval reasons...')
                        ->rows(5),
                ])
                ->action(function (array $data) {
                    $newStatus = ($this->record->status === 'Initial Assessment' || blank($this->record->status)) ? 'For Notary' : $this->record->status;
                    $this->record->update([
                        'approval_status' => 'Approved',
                        'status'          => $newStatus,
                        'loan_account_no' => $data['loan_account_no'] ?? $this->record->loan_account_no,
                        'date_granted'    => $data['date_granted'] ?? $this->record->date_granted,
                        'maturity_date'   => $data['maturity_date'] ?? $this->record->maturity_date,
                    ]);

                    HandlesApprovalWorkflow::sendApprovalNotification($this->record, auth()->user());

                    if (filled($data['approval_notes'] ?? null)) {
                        $this->record->applicationNotes()->create([
                            'user_id' => auth()->id() ?? $this->record->created_by_user_id,
                            'stage'   => ($this->record->status ?? 'Initial Assessment') . ' (Approval Comment)',
                            'note'    => $data['approval_notes'],
                        ]);
                    }

                    Notification::make()
                        ->title('✅ Loan Application Approved')
                        ->body("Application {$this->record->application_no} for " . ($this->record->member?->full_name ?? 'Borrower') . " has been APPROVED.")
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('index'));
                }),

            Actions\Action::make('disapprove_loan')
                ->label('Disapprove Loan')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->modalHeading(fn (): string => "Disapprove Loan: {$this->record->application_no}")
                ->modalDescription('Specify the deficiency type and reason for disapproving this loan application.')
                ->modalSubmitActionLabel('Confirm Disapproval')
                ->modalIcon('heroicon-o-x-circle')
                ->modalIconColor('danger')
                ->visible(fn (): bool => ! in_array($this->record->approval_status, ['Disapproved']) && ! auth()->user()?->isBranchUser())
                ->form([
                    Radio::make('deficiency_type')
                        ->label('Deficiency Type')
                        ->options([
                            'internal_deficiency' => '🏢 Internal Deficiency — Missing/incomplete documents from the branch or internal process',
                            'external_deficiency' => '🏛️ External Deficiency — Issues with external agencies (BIR, ROD, LTO, Assessor, etc.)',
                        ])
                        ->required()
                        ->columnSpanFull(),
                    Textarea::make('disapproval_reason')
                        ->label('Comment / Disapproval Reason')
                        ->placeholder('Specify the required corrections, missing documents, or disapproval reason...')
                        ->required()
                        ->rows(5),
                ])
                ->action(function (array $data) {
                    $currentNotes = $this->record->notes ? $this->record->notes . "\n\n" : '';
                    $this->record->update([
                        'approval_status'  => 'Disapproved',
                        'sub_status'       => $data['deficiency_type'] ?? 'none',
                        'deficiency_notes' => $data['disapproval_reason'],
                        'notes'            => $currentNotes . "[DISAPPROVAL REASON - " . now()->format('F d, Y h:i A') . "]:\n" . $data['disapproval_reason'],
                    ]);

                    HandlesApprovalWorkflow::sendApprovalNotification($this->record, auth()->user());

                    if (filled($data['disapproval_reason'] ?? null)) {
                        $this->record->applicationNotes()->create([
                            'user_id' => auth()->id() ?? $this->record->created_by_user_id,
                            'stage'   => ($this->record->status ?? 'Initial Assessment') . ' (Disapproved Comment)',
                            'note'    => $data['disapproval_reason'],
                        ]);
                    }

                    Notification::make()
                        ->title('❌ Loan Application Disapproved')
                        ->body("Application {$this->record->application_no} has been marked as DISAPPROVED.")
                        ->danger()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('index'));
                }),

            Actions\DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->can('Delete:LoanApplication') && (auth()->user()?->isHeadOfficeUser() || auth()->user()?->hasRole(['super_admin', 'Super Admin']))),
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
            'Approved'       => \App\Filament\Resources\LoanApplicationResource::getUrl('index'),
            'Disapproved'    => \App\Filament\Resources\DisapprovedLoanResource::getUrl('index'),
            'Branch Updated' => \App\Filament\Resources\BranchUpdateResource::getUrl('index'),
            default          => static::getResource()::getUrl('index'),
        };

        $this->redirect($url);
    }

    protected function getRedirectUrl(): ?string
    {
        return match ($this->record->approval_status) {
            'Approved'       => \App\Filament\Resources\LoanApplicationResource::getUrl('index'),
            'Disapproved'    => \App\Filament\Resources\DisapprovedLoanResource::getUrl('index'),
            'Branch Updated' => \App\Filament\Resources\BranchUpdateResource::getUrl('index'),
            default          => null,
        };
    }
}
