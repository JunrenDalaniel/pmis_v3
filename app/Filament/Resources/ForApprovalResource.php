<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ForApprovalResource\Pages;
use App\Models\LoanApplication;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class ForApprovalResource extends Resource
{
    protected static ?string $model = LoanApplication::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static string | UnitEnum | null $navigationGroup = 'Loan Operations';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Pending Approval';

    protected static ?string $modelLabel = 'Pending Approval Loan';

    protected static ?string $pluralModelLabel = 'Pending Approval';

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
            ->where('approval_status', 'Pending Approval')
            ->with(['member', 'branchOffice', 'chattelCollaterals', 'realEstateCollaterals']);

        $user = auth()->user();
        if ($user) {
            $allowedBranches = $user->getAllowedBranchIds();
            if (is_array($allowedBranches)) {
                $query->whereIn('branch_office_id', $allowedBranches);
            }
        }

        return $query;
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
                    LoanApplicationResource::getApproveTableAction(),
                    LoanApplicationResource::getDisapproveTableAction(),
                    LoanApplicationResource::getDownloadArchiveTableAction(),
                    EditAction::make(),
                    DeleteAction::make()
                        ->visible(fn (LoanApplication $record): bool => auth()->user()?->can('Delete:LoanApplication') && (auth()->user()?->isHeadOfficeUser() || auth()->user()?->hasRole(['super_admin', 'Super Admin']))),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListForApprovals::route('/'),
            'edit' => Pages\EditForApproval::route('/{record}/edit'),
        ];
    }
}
