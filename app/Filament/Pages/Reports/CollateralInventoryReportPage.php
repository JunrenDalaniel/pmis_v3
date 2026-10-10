<?php

namespace App\Filament\Pages\Reports;

use App\Models\BranchOffice;
use App\Models\LoanApplication;
use App\Services\ReportExportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class CollateralInventoryReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $title = 'Collateral & Encumbrance Inventory';

    protected static ?string $navigationLabel = 'Collateral Inventory';

    protected static string | UnitEnum | null $navigationGroup = 'Reports & Analytics';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'reports/collateral-inventory';

    protected string $view = 'filament.pages.reports.report-page';

    public string $report_type = 'collateral_inventory';
    public string $date_preset = 'all';
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?string $branch_office_id = null;
    public ?string $collateral_type = null;

    public ?array $previewData = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print Official PDF')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->action(function () {
                    $url = $this->getPrintUrl() . '&autoprint=1';
                    $this->dispatch('print-report-silent', url: $url);
                }),

            Action::make('export_excel')
                ->label('Export to Excel (.xlsx)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->url(fn () => $this->getExcelUrl()),
        ];
    }

    public function mount(): void
    {
        $this->generatePreview();
    }

    public function resetFilters(): void
    {
        $this->date_preset = 'all';
        $this->startDate = null;
        $this->endDate = null;
        $this->branch_office_id = null;
        $this->collateral_type = null;
        $this->resetTable();
        $this->generatePreview();
    }

    public function updatedDatePreset(): void
    {
        $this->resetTable();
        $this->generatePreview();
    }

    public function updatedBranchOfficeId(): void
    {
        $this->resetTable();
        $this->generatePreview();
    }

    public function updatedCollateralType(): void
    {
        $this->resetTable();
        $this->generatePreview();
    }

    public function updatedStartDate(): void
    {
        $this->resetTable();
        $this->generatePreview();
    }

    public function updatedEndDate(): void
    {
        $this->resetTable();
        $this->generatePreview();
    }

    protected function getDateRange(): array
    {
        return ReportExportService::resolveDateRange([
            'date_preset' => $this->date_preset,
            'startDate'   => $this->startDate,
            'endDate'     => $this->endDate,
        ]);
    }

    public function generatePreview(): void
    {
        $filters = [
            'date_preset'      => $this->date_preset,
            'startDate'        => $this->startDate,
            'endDate'          => $this->endDate,
            'branch_office_id' => $this->branch_office_id,
            'collateral_type'  => $this->collateral_type,
        ];

        $this->previewData = ReportExportService::getReportData($this->report_type, $filters, auth()->user());
    }

    public function table(Table $table): Table
    {
        [$startDate, $endDate] = $this->getDateRange();
        $allowed = auth()->user()?->getAllowedBranchIds();

        $query = LoanApplication::query()
            ->with(['member', 'branchOffice', 'chattelCollaterals', 'realEstateCollaterals'])
            ->where('approval_status', 'Approved');

        if (is_array($allowed)) {
            $query->whereIn('branch_office_id', $allowed);
        }
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        if (filled($this->branch_office_id)) {
            $query->where('branch_office_id', $this->branch_office_id);
        }
        if (filled($this->collateral_type)) {
            if ($this->collateral_type === 'real_estate_mortgage') {
                $query->where(fn ($q) => $q->where('collateral_type', 'like', '%real%')->orWhere('collateral_type', 'like', '%rem%'));
            } elseif ($this->collateral_type === 'chattel_mortgage') {
                $query->where(fn ($q) => $q->where('collateral_type', 'like', '%chattel%')->orWhere('collateral_type', 'like', '%cm%'));
            }
        }

        return $table
            ->query($query->orderByDesc('created_at'))
            ->striped()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->persistColumnSearchesInSession()
            ->emptyStateHeading('No Collateral Assets Found')
            ->emptyStateDescription('Try adjusting your date range or OIC branch filter.')
            ->emptyStateIcon('heroicon-o-shield-check')
            ->columns([
                TextColumn::make('application_no')
                    ->label('App ID')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->color('primary')
                    ->copyable()
                    ->copyMessage('Application ID copied to clipboard'),
                TextColumn::make('member.full_name')
                    ->label('Borrower Name')
                    ->formatStateUsing(fn ($state, $record) => $record?->member?->natural_full_name ?? $record?->member?->full_name ?? $state)
                    ->sortable(['last_name', 'first_name'])
                    ->searchable(['last_name', 'first_name']),
                TextColumn::make('branchOffice.name')
                    ->label('Branch')
                    ->sortable(),
                TextColumn::make('collateral_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => str_contains((string) $state, 'real') ? 'REM' : 'CM'),
                TextColumn::make('requested_amount')
                    ->label('Loan Amount')
                    ->money('PHP')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->summarize(
                        Sum::make()
                            ->money('PHP')
                            ->label('Total Collateral Loan Volume')
                            ->extraAttributes([
                                'style' => 'text-align: right; justify-items: end; display: flex; flex-direction: column; align-items: flex-end;',
                            ])
                    ),
                TextColumn::make('collateral_ident')
                    ->label('Collateral Identification')
                    ->state(function (LoanApplication $record) {
                        if (str_contains(strtolower((string) $record->collateral_type), 'real') && $record->realEstateCollaterals->isNotEmpty()) {
                            return 'Title: ' . ($record->realEstateCollaterals->first()->title_number ?: 'N/A');
                        } elseif ($record->chattelCollaterals->isNotEmpty()) {
                            $cm = $record->chattelCollaterals->first();
                            return 'Plate: ' . ($cm->plate_no ?: ($cm->mv_file_no ?: 'N/A'));
                        }
                        return 'N/A';
                    }),
                TextColumn::make('reg_details')
                    ->label('Registration Details')
                    ->state(function (LoanApplication $record) {
                        if (str_contains(strtolower((string) $record->collateral_type), 'real') && $record->realEstateCollaterals->isNotEmpty()) {
                            $rem = $record->realEstateCollaterals->first();
                            return ($rem->location ? "Loc: {$rem->location}" : '') . ($rem->tax_dec_land ? " • TaxDec: {$rem->tax_dec_land}" : '');
                        } elseif ($record->chattelCollaterals->isNotEmpty()) {
                            $cm = $record->chattelCollaterals->first();
                            return ($cm->year_model ? "{$cm->year_model} " : '') . ($cm->vehicle_description ?: 'Vehicle');
                        }
                        return 'N/A';
                    })
                    ->limit(35),
                TextColumn::make('encumbrance_status')
                    ->label('Encumbrance Status')
                    ->badge()
                    ->state(fn (LoanApplication $record) => $record->isEncumbered() ? 'SECURED' : 'ON-PROCESS')
                    ->color(fn ($state) => $state === 'SECURED' ? 'success' : 'warning'),
                TextColumn::make('status')
                    ->label('Pipeline Status')
                    ->badge(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (LoanApplication $record) => route('filament.admin.resources.loan-applications.view', $record)),
            ])
            ->defaultPaginationPageOption(10);
    }

    protected function getViewData(): array
    {
        return [
            'branches'             => $this->branches,
            'printUrl'             => $this->getPrintUrl(),
            'excelUrl'             => $this->getExcelUrl(),
            'hasCollateralFilter'  => true,
        ];
    }

    public function getBranchesProperty(): array
    {
        $user = auth()->user();
        $allowed = $user?->getAllowedBranchIds();
        $q = BranchOffice::query()->where('is_active', true)->orderBy('name');
        if (is_array($allowed)) {
            $q->whereIn('id', $allowed);
        }
        return $q->pluck('name', 'id')->toArray();
    }

    public function getPrintUrl(): string
    {
        $params = http_build_query([
            'report_type'      => $this->report_type,
            'date_preset'      => $this->date_preset,
            'startDate'        => $this->startDate,
            'endDate'          => $this->endDate,
            'branch_office_id' => $this->branch_office_id,
            'collateral_type'  => $this->collateral_type,
        ]);

        return route('reports.print') . '?' . $params;
    }

    public function getExcelUrl(): string
    {
        $params = http_build_query([
            'report_type'      => $this->report_type,
            'date_preset'      => $this->date_preset,
            'startDate'        => $this->startDate,
            'endDate'          => $this->endDate,
            'branch_office_id' => $this->branch_office_id,
            'collateral_type'  => $this->collateral_type,
        ]);

        return route('reports.export-excel') . '?' . $params;
    }
}
