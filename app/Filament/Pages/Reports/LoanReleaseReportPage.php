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

class LoanReleaseReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $title = 'Loan Release & Portfolio Summary';

    protected static ?string $navigationLabel = 'Loan Release';

    protected static string | UnitEnum | null $navigationGroup = 'Reports & Analytics';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'reports/loan-release';

    protected string $view = 'filament.pages.reports.report-page';

    public string $report_type = 'loan_release';
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
            ->with(['member', 'branchOffice'])
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
            ->emptyStateHeading('No Loan Releases Found')
            ->emptyStateDescription('Try adjusting your date range, OIC branch, or collateral type filter.')
            ->emptyStateIcon('heroicon-o-document-currency-dollar')
            ->columns([
                TextColumn::make('application_no')
                    ->label('Application ID')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->color('primary')
                    ->copyable()
                    ->copyMessage('Application ID copied to clipboard'),
                TextColumn::make('created_at')
                    ->date('Y-m-d')
                    ->sortable()
                    ->label('Date Filed'),
                TextColumn::make('member.full_name')
                    ->label('Borrower Name')
                    ->formatStateUsing(fn ($state, $record) => $record?->member?->natural_full_name ?? $record?->member?->full_name ?? $state)
                    ->sortable(['last_name', 'first_name'])
                    ->searchable(['last_name', 'first_name']),
                TextColumn::make('member.member_no')
                    ->label('Member No.')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->copyable()
                    ->copyMessage('Member No. copied to clipboard'),
                TextColumn::make('branchOffice.name')
                    ->label('OIC Branch')
                    ->sortable(),
                TextColumn::make('collateral_type')
                    ->label('Collateral')
                    ->badge()
                    ->formatStateUsing(fn ($state) => str($state)->headline())
                    ->color(fn ($state) => str_contains((string) $state, 'real') ? 'info' : 'warning'),
                TextColumn::make('requested_amount')
                    ->label('Loan Amount')
                    ->money('PHP')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success')
                    ->summarize(
                        Sum::make()
                            ->money('PHP')
                            ->label('Total Loan Volume')
                            ->extraAttributes([
                                'style' => 'text-align: right; justify-items: end; display: flex; flex-direction: column; align-items: flex-end;',
                            ])
                    ),
                TextColumn::make('date_granted')
                    ->date('Y-m-d')
                    ->sortable()
                    ->label('Date Granted')
                    ->placeholder('Pending'),
                TextColumn::make('maturity_date')
                    ->date('Y-m-d')
                    ->sortable()
                    ->label('Maturity Date')
                    ->placeholder('Pending')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Pipeline Stage')
                    ->badge(),
                TextColumn::make('sub_status')
                    ->label('Deficiency')
                    ->badge()
                    ->formatStateUsing(fn ($state) => !empty($state) && $state !== 'none' ? strtoupper(str_replace('_', ' ', (string) $state)) : 'CLEAN')
                    ->color(fn ($state) => !empty($state) && $state !== 'none' ? 'danger' : 'success'),
                TextColumn::make('notes')
                    ->label('Remarks')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
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
