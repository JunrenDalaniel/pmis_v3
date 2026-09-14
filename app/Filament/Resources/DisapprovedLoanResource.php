<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DisapprovedLoanResource\Pages;
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

class DisapprovedLoanResource extends Resource
{
    protected static ?string $model = LoanApplication::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-x-circle';

    protected static string | UnitEnum | null $navigationGroup = 'Loan Operations';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Non-Released Loans';

    protected static ?string $modelLabel = 'Non-Released Loan';

    protected static ?string $pluralModelLabel = 'Non-Released Loans';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('approval_status', 'Disapproved')
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
                    LoanApplicationResource::getResubmitTableAction(),
                    LoanApplicationResource::getDownloadArchiveTableAction(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDisapprovedLoans::route('/'),
            'edit' => Pages\EditDisapprovedLoan::route('/{record}/edit'),
        ];
    }
}
