<?php

namespace App\Filament\Resources\MemberResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class LoanApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'loanApplications';

    protected static ?string $title = 'Loan Applications & Mortgage History';

    protected static string | \BackedEnum | null $icon = 'heroicon-o-document-duplicate';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('application_no')
            ->columns([
                Tables\Columns\TextColumn::make('application_no')
                    ->label('Application ID')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Application ID copied!')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date Filed')
                    ->dateTime('M d, Y')
                    ->icon('heroicon-o-calendar')
                    ->sortable(),
                Tables\Columns\TextColumn::make('branchOffice.name')
                    ->label('Branch')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('requested_amount')
                    ->label('Loan Amount')
                    ->description(fn (\App\Models\LoanApplication $record): ?string => in_array($record->approval_status, ['Approved', 'Branch Updated']) ? null : 'Requested')
                    ->money('PHP')
                    ->weight('bold')
                    ->sortable(),
                Tables\Columns\TextColumn::make('collateral_type')
                    ->label('Collateral Type')
                    ->badge()
                    ->color(fn ($state) => $state === 'real_estate_mortgage' ? 'primary' : 'warning')
                    ->formatStateUsing(fn ($state) => $state === 'real_estate_mortgage' ? 'Real Estate (REM)' : 'Chattel (CM)'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Initial Assessment' => 'gray',
                        'For Notary' => 'warning',
                        'For BIR' => 'primary',
                        'For ROD' => 'success',
                        default => 'info',
                    })
                    ->description(function (\App\Models\LoanApplication $record): ?\Illuminate\Support\HtmlString {
                        $ref = $record->status_changed_at ? \Carbon\Carbon::parse($record->status_changed_at) : ($record->created_at ? \Carbon\Carbon::parse($record->created_at) : now());
                        $days = (int) $ref->diffInDays(now());

                        if ($days >= 11) {
                            $color = '#ef4444';
                            $icon = '🔴';
                        } elseif ($days >= 6) {
                            $color = '#f59e0b';
                            $icon = '🟡';
                        } else {
                            $color = '#22c55e';
                            $icon = '🟢';
                        }

                        $dayText = $days === 0 ? 'today' : ($days === 1 ? '1 day' : "{$days} days");
                        return new \Illuminate\Support\HtmlString("<span style='font-size: 10px; font-weight: 500; color: {$color}; display: block; margin-top: 2px;'>{$icon} {$dayText} in this stage</span>");
                    })
                    ->sortable(),
            ])
            ->actions([
                Action::make('download_archive')
                    ->label('Download Archive')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('📥 Download Encrypted Documents Archive')
                    ->modalIcon('heroicon-o-lock-closed')
                    ->modalIconColor('success')
                    ->modalDescription(function (\App\Models\LoanApplication $record) {
                        $memberNo = $record->member?->member_no ?? 'MEMBER-GENERAL';
                        $borrowerName = $record->member?->full_name ?? 'Borrower';
                        $appNo = $record->application_no ?? 'LN-GENERAL';
                        $cleanMemberNo = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $memberNo);
                        $cleanName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '', (string) $borrowerName);
                        $cleanName = preg_replace('/,{2,}/', ',', trim($cleanName));
                        $expectedFileName = "{$cleanMemberNo}_{$cleanName}.zip";

                        return new \Illuminate\Support\HtmlString("
                            <style>
                                .pmis-modal-card {
                                    background: #ffffff;
                                    border: 1px solid #e2e8f0;
                                    color: #1e293b;
                                    border-radius: 10px;
                                    padding: 16px;
                                    font-size: 13px;
                                    text-align: left;
                                }
                                .dark .pmis-modal-card {
                                    background: #18181b;
                                    border: 1px solid #3f3f46;
                                    color: #f4f4f5;
                                }
                                .pmis-row {
                                    display: flex;
                                    align-items: center;
                                    justify-content: space-between;
                                    gap: 12px;
                                    padding: 7px 0;
                                    border-bottom: 1px solid #f1f5f9;
                                }
                                .dark .pmis-row {
                                    border-bottom-color: #27272a;
                                }
                                .pmis-label {
                                    color: #64748b;
                                    font-size: 12px;
                                    font-weight: 600;
                                }
                                .dark .pmis-label {
                                    color: #a1a1aa;
                                }
                                .pmis-val {
                                    color: #0f172a;
                                    font-weight: 700;
                                    font-size: 12.5px;
                                }
                                .dark .pmis-val {
                                    color: #ffffff;
                                }
                                .pmis-file-badge {
                                    background: #e0f2fe;
                                    color: #0369a1;
                                    padding: 3px 8px;
                                    border-radius: 6px;
                                    font-family: monospace;
                                    font-size: 12px;
                                    font-weight: 700;
                                }
                                .dark .pmis-file-badge {
                                    background: #082f49;
                                    color: #38bdf8;
                                    border: 1px solid #0369a1;
                                }
                                .pmis-pwd-box {
                                    margin-top: 12px;
                                    padding: 12px 14px;
                                    background: #ecfdf5;
                                    border: 1px solid #a7f3d0;
                                    border-radius: 8px;
                                }
                                .dark .pmis-pwd-box {
                                    background: #022c22;
                                    border: 1px solid #059669;
                                }
                                .pmis-pwd-heading {
                                    font-size: 11px;
                                    font-weight: 700;
                                    text-transform: uppercase;
                                    letter-spacing: 0.05em;
                                    color: #047857;
                                }
                                .dark .pmis-pwd-heading {
                                    color: #34d399;
                                }
                                .pmis-pwd-code {
                                    font-size: 18px;
                                    font-weight: 800;
                                    color: #065f46;
                                    font-family: monospace;
                                    letter-spacing: 0.5px;
                                    margin-top: 4px;
                                    user-select: all;
                                    cursor: pointer;
                                }
                                .dark .pmis-pwd-code {
                                    color: #6ee7b7;
                                }
                                .pmis-hint {
                                    margin-top: 12px;
                                    font-size: 11.5px;
                                    line-height: 1.5;
                                    color: #64748b;
                                }
                                .dark .pmis-hint {
                                    color: #a1a1aa;
                                }
                            </style>
                            <div class='pmis-modal-card'>
                                <div class='pmis-row' style='padding-top: 0;'>
                                    <span class='pmis-label'>📄 File Name:</span>
                                    <span class='pmis-file-badge'>" . e($expectedFileName) . "</span>
                                </div>
                                <div class='pmis-row'>
                                    <span class='pmis-label'>👤 Borrower:</span>
                                    <span class='pmis-val'>" . e($borrowerName) . "</span>
                                </div>
                                <div class='pmis-row' style='border-bottom: none;'>
                                    <span class='pmis-label'>📋 Loan App:</span>
                                    <span class='pmis-val'>" . e($appNo) . "</span>
                                </div>
                                <div class='pmis-pwd-box'>
                                    <div class='pmis-pwd-heading'>🔑 Password to Extract (Member ID):</div>
                                    <div class='pmis-pwd-code' title='Click to highlight and copy'>" . e($memberNo) . "</div>
                                </div>
                                <div class='pmis-hint'>
                                    Use the <strong style='color: inherit;'>Member ID</strong> above as the password when opening or extracting the ZIP file using 7-Zip, WinRAR, or Windows Extractor.
                                </div>
                            </div>
                        ");
                    })
                    ->modalSubmitActionLabel('Download Archive')
                    ->modalCancelActionLabel('Cancel')
                    ->action(function (\App\Models\LoanApplication $record, $livewire) {
                        $downloadUrl = route('loans.download-archive', ['record' => $record->id]);
                        $password = $record->member?->member_no ?? 'MEMBER-GENERAL';

                        \Filament\Notifications\Notification::make()
                            ->title('📦 Archive Downloading')
                            ->body("Password: {$password}")
                            ->success()
                            ->send();

                        $livewire->js("window.location.href = '{$downloadUrl}';");
                    }),
                Action::make('view_application')
                    ->label('Open / Edit Loan Record')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn ($record) => \App\Filament\Resources\LoanApplicationResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
