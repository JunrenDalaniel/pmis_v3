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
        if (! $user) {
            return $query;
        }

        if ($user->hasRole(['super_admin', 'Super Admin']) || in_array($user->role?->code, ['super_admin', 'operation_division_manager', 'chief_executive_officer', 'audit', 'compliance_specialist', 'appraiser'])) {
            return $query;
        }

        if ($user->hasRole(['Area Manager 1 (AM1)', 'area_manager_1']) || $user->role?->code === 'area_manager_1') {
            return $query->whereHas('branchOffice', fn ($b) => $b->where('area', 'like', '%1%'));
        }
        if ($user->hasRole(['Area Manager 2 (AM2)', 'area_manager_2']) || $user->role?->code === 'area_manager_2') {
            return $query->whereHas('branchOffice', fn ($b) => $b->where('area', 'like', '%2%'));
        }
        if ($user->hasRole(['Area Manager 3 (AM3)', 'area_manager_3']) || $user->role?->code === 'area_manager_3') {
            return $query->whereHas('branchOffice', fn ($b) => $b->where('area', 'like', '%3%'));
        }

        if ($user->branch_office_id !== null) {
            $query->where('branch_office_id', $user->branch_office_id);
        } else {
            // No branch assigned — return no results to prevent data leak
            return $query->whereRaw('1 = 0');
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
                    LoanApplicationResource::getApproveTableAction(),
                    LoanApplicationResource::getDisapproveTableAction(),
                    LoanApplicationResource::getDownloadArchiveTableAction(),
                    EditAction::make(),
                    DeleteAction::make(),
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
