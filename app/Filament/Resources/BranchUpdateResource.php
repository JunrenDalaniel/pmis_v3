<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HandlesApprovalWorkflow;
use App\Filament\Resources\BranchUpdateResource\Pages;
use App\Models\LoanApplication;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class BranchUpdateResource extends Resource
{
    protected static ?string $model = LoanApplication::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string | UnitEnum | null $navigationGroup = 'Loan Operations';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Branch Updates';

    protected static ?string $modelLabel = 'Branch Updated Loan';

    protected static ?string $pluralModelLabel = 'Branch Updates';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('approval_status', 'Branch Updated')
            ->with(['member', 'branchOffice', 'chattelCollaterals', 'realEstateCollaterals']);

        $user = auth()->user();
        if (! $user) {
            return $query;
        }

        if ($user->hasRole(['Super Admin', 'super_admin']) || $user->role?->code === 'super_admin') {
            return $query;
        }

        $allowedBranchIds = $user->getAllowedBranchIds();
        if (! empty($allowedBranchIds)) {
            return $query->whereIn('branch_office_id', $allowedBranchIds);
        }

        // Fallback: if getAllowedBranchIds() returns empty, scope to own branch to prevent unscoped query
        if ($user->branch_office_id !== null) {
            return $query->where('branch_office_id', $user->branch_office_id);
        }

        // Last resort: return no results rather than all records
        return $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return LoanApplicationResource::form($schema);
    }

    public static function table(Table $table): Table
    {
        return LoanApplicationResource::table($table)
            ->actions([
                ActionGroup::make([
                    LoanApplicationResource::getViewDocumentsTableAction(),
                    LoanApplicationResource::getQuickUploadTableAction(),
                    Action::make('mark_verified')
                        ->label('Mark Verified / Clear Update')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn () => ! (
                            auth()->user()?->isBranchUser()
                            || auth()->user()?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist', 'Branch Manager', 'branch_manager'])
                            || in_array(auth()->user()?->role?->code, ['mortgage_document_specialist', 'branch_manager'])
                        ))
                        ->requiresConfirmation()
                        ->modalHeading('Confirm Document & Stage Verification')
                        ->modalDescription('Mark this branch update as verified by Head Office. The loan will return to Approved status.')
                        ->modalSubmitActionLabel('Confirm Verification')
                        ->action(function (LoanApplication $record, $livewire): void {
                            $record->approval_status = 'Approved';
                            $record->sub_status = 'none';
                            $record->save();

                            // Send DB bell notification to current user + loan creator
                            HandlesApprovalWorkflow::sendApprovalNotification($record, auth()->user());

                            Notification::make()
                                ->title('✅ Branch Update Verified')
                                ->body("Application {$record->application_no} update has been verified. Loan is back to Approved status.")
                                ->success()
                                ->send();

                            $livewire->redirect(url()->current());
                        }),
                    LoanApplicationResource::getDownloadArchiveTableAction(),
                    EditAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranchUpdates::route('/'),
            'edit'  => Pages\EditBranchUpdate::route('/{record}/edit'),
        ];
    }
}
