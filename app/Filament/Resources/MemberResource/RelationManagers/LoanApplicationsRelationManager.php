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
                    ->label('Requested Amount')
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
                    ->action(function (\App\Models\LoanApplication $record) {
                        $memberNo = $record->member?->member_no ?? 'MEMBER-GENERAL';
                        $appNo = $record->application_no ?? 'LN-GENERAL';
                        $password = $memberNo;

                        $safeMemberNo = str_replace(['/', '\\'], '-', $memberNo);
                        $safeAppNo = str_replace(['/', '\\'], '-', $appNo);

                        $basePath = storage_path("app/private/{$safeMemberNo}/{$safeAppNo}");

                        if (!file_exists($basePath)) {
                            \Filament\Notifications\Notification::make()
                                ->title('No Files Found')
                                ->body("No document files found for application {$appNo}.")
                                ->warning()
                                ->send();
                            return;
                        }

                        $tempZipPath = storage_path("app/private/temp_{$safeAppNo}_" . time() . ".zip");
                        $zip = new \ZipArchive();

                        if ($zip->open($tempZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                            \Filament\Notifications\Notification::make()
                                ->title('Archive Error')
                                ->body('Unable to generate zip archive file.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $zip->setPassword($password);

                        $files = new \RecursiveIteratorIterator(
                            new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                            \RecursiveIteratorIterator::LEAVES_ONLY
                        );

                        $fileCount = 0;
                        foreach ($files as $file) {
                            if (!$file->isDir()) {
                                $filePath = $file->getRealPath();
                                $relativePath = substr($filePath, strlen($basePath) + 1);
                                $zipEntryName = "{$safeAppNo}/" . str_replace('\\', '/', $relativePath);

                                $zip->addFile($filePath, $zipEntryName);
                                if (defined('\ZipArchive::EM_AES_256')) {
                                    $zip->setEncryptionName($zipEntryName, \ZipArchive::EM_AES_256);
                                }
                                $fileCount++;
                            }
                        }

                        $zip->close();

                        if ($fileCount === 0) {
                            if (file_exists($tempZipPath)) {
                                unlink($tempZipPath);
                            }
                            \Filament\Notifications\Notification::make()
                                ->title('No Files to Download')
                                ->body("No files are currently stored in application {$appNo}.")
                                ->warning()
                                ->send();
                            return;
                        }

                        $downloadFileName = $record->member?->full_name ? str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $record->member->full_name) : $safeAppNo;

                        return response()->download($tempZipPath, "{$downloadFileName}.zip")->deleteFileAfterSend(true);
                    }),
                Action::make('view_application')
                    ->label('Open / Edit Loan Record')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn ($record) => \App\Filament\Resources\LoanApplicationResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
