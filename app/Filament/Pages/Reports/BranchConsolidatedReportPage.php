<?php

namespace App\Filament\Pages\Reports;

use App\Models\BranchOffice;
use App\Models\LoanApplication;
use App\Services\ReportExportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class BranchConsolidatedReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $title = 'Consolidated Branch Matrix';

    protected static ?string $navigationLabel = 'Consolidated Branches';

    protected static string | UnitEnum | null $navigationGroup = 'Reports & Analytics';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'reports/consolidated-branches';

    protected string $view = 'filament.pages.reports.report-page';

    public string $report_type = 'branch_consolidated';
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

        $query = BranchOffice::query()->where('is_active', true)->orderBy('name');
        if (is_array($allowed)) {
            $query->whereIn('id', $allowed);
        }
        if (filled($this->branch_office_id)) {
            $query->where('id', $this->branch_office_id);
        }

        return $table
            ->query($query)
            ->striped()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->emptyStateHeading('No Branch Office Records Found')
            ->emptyStateDescription('No branch offices match the selected scope filter.')
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->columns([
                TextColumn::make('name')
                    ->label('OIC Branch')
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('cm_count')
                    ->label('Chattel (CM)')
                    ->alignCenter()
                    ->state(function (BranchOffice $record) use ($startDate, $endDate) {
                        $q = $record->loanApplications()->where('approval_status', 'Approved')->where(fn ($sq) => $sq->where('collateral_type', 'like', '%chattel%')->orWhere('collateral_type', 'like', '%cm%'));
                        if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                        if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                        return $q->count();
                    })
                    ->summarize(
                        Summarizer::make()
                            ->label('Total CM')
                            ->using(function ($query) use ($startDate, $endDate) {
                                $branchIds = (clone $query)->pluck('id');
                                $q = LoanApplication::whereIn('branch_office_id', $branchIds)
                                    ->where('approval_status', 'Approved')
                                    ->where(fn ($sq) => $sq->where('collateral_type', 'like', '%chattel%')->orWhere('collateral_type', 'like', '%cm%'));
                                if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                                if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                                return $q->count();
                            })
                    ),
                TextColumn::make('rem_count')
                    ->label('Real Estate (REM)')
                    ->alignCenter()
                    ->state(function (BranchOffice $record) use ($startDate, $endDate) {
                        $q = $record->loanApplications()->where('approval_status', 'Approved')->where(fn ($sq) => $sq->where('collateral_type', 'like', '%real%')->orWhere('collateral_type', 'like', '%rem%'));
                        if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                        if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                        return $q->count();
                    })
                    ->summarize(
                        Summarizer::make()
                            ->label('Total REM')
                            ->using(function ($query) use ($startDate, $endDate) {
                                $branchIds = (clone $query)->pluck('id');
                                $q = LoanApplication::whereIn('branch_office_id', $branchIds)
                                    ->where('approval_status', 'Approved')
                                    ->where(fn ($sq) => $sq->where('collateral_type', 'like', '%real%')->orWhere('collateral_type', 'like', '%rem%'));
                                if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                                if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                                return $q->count();
                            })
                    ),
                TextColumn::make('total_volume')
                    ->label('Total Approved (₱)')
                    ->money('PHP')
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success')
                    ->state(function (BranchOffice $record) use ($startDate, $endDate) {
                        $q = $record->loanApplications()->where('approval_status', 'Approved');
                        if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                        if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                        return (float) $q->sum('requested_amount');
                    })
                    ->summarize(
                        Summarizer::make()
                            ->money('PHP')
                            ->label('Grand Total Volume')
                            ->using(function ($query) use ($startDate, $endDate) {
                                $branchIds = (clone $query)->pluck('id');
                                $q = LoanApplication::whereIn('branch_office_id', $branchIds)->where('approval_status', 'Approved');
                                if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                                if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                                return (float) $q->sum('requested_amount');
                            })
                    ),
                TextColumn::make('enc_cm')
                    ->label('Encumbered CM')
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->state(function (BranchOffice $record) use ($startDate, $endDate) {
                        $q = $record->loanApplications()->where('approval_status', 'Approved')->where(fn ($sq) => $sq->where('collateral_type', 'like', '%chattel%')->orWhere('collateral_type', 'like', '%cm%'))->whereIn('status', ['Encumbered', 'ROD Released', 'Rod Released']);
                        if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                        if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                        return $q->count();
                    })
                    ->summarize(
                        Summarizer::make()
                            ->label('Total Enc CM')
                            ->using(function ($query) use ($startDate, $endDate) {
                                $branchIds = (clone $query)->pluck('id');
                                $q = LoanApplication::whereIn('branch_office_id', $branchIds)->where('approval_status', 'Approved')->where(fn ($sq) => $sq->where('collateral_type', 'like', '%chattel%')->orWhere('collateral_type', 'like', '%cm%'))->whereIn('status', ['Encumbered', 'ROD Released', 'Rod Released']);
                                if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                                if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                                return $q->count();
                            })
                    ),
                TextColumn::make('enc_rem')
                    ->label('Encumbered REM')
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->state(function (BranchOffice $record) use ($startDate, $endDate) {
                        $q = $record->loanApplications()->where('approval_status', 'Approved')->where(fn ($sq) => $sq->where('collateral_type', 'like', '%real%')->orWhere('collateral_type', 'like', '%rem%'))->whereIn('status', ['Encumbered', 'ROD Released', 'Rod Released']);
                        if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                        if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                        return $q->count();
                    })
                    ->summarize(
                        Summarizer::make()
                            ->label('Total Enc REM')
                            ->using(function ($query) use ($startDate, $endDate) {
                                $branchIds = (clone $query)->pluck('id');
                                $q = LoanApplication::whereIn('branch_office_id', $branchIds)->where('approval_status', 'Approved')->where(fn ($sq) => $sq->where('collateral_type', 'like', '%real%')->orWhere('collateral_type', 'like', '%rem%'))->whereIn('status', ['Encumbered', 'ROD Released', 'Rod Released']);
                                if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                                if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                                return $q->count();
                            })
                    ),
                TextColumn::make('on_process')
                    ->label('On Process')
                    ->alignCenter()
                    ->badge()
                    ->color('warning')
                    ->state(function (BranchOffice $record) use ($startDate, $endDate) {
                        $q = $record->loanApplications()->where('approval_status', 'Approved')->whereNotIn('status', ['Encumbered', 'ROD Released', 'Rod Released']);
                        if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                        if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                        return $q->count();
                    })
                    ->summarize(
                        Summarizer::make()
                            ->label('Total In-Process')
                            ->using(function ($query) use ($startDate, $endDate) {
                                $branchIds = (clone $query)->pluck('id');
                                $q = LoanApplication::whereIn('branch_office_id', $branchIds)->where('approval_status', 'Approved')->whereNotIn('status', ['Encumbered', 'ROD Released', 'Rod Released']);
                                if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                                if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                                return $q->count();
                            })
                    ),
                TextColumn::make('deficiency')
                    ->label('Deficiency')
                    ->alignCenter()
                    ->badge()
                    ->color('danger')
                    ->state(function (BranchOffice $record) use ($startDate, $endDate) {
                        $q = $record->loanApplications()->whereIn('sub_status', ['internal_deficiency', 'external_deficiency']);
                        if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                        if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                        return $q->count();
                    })
                    ->summarize(
                        Summarizer::make()
                            ->label('Total Deficiencies')
                            ->using(function ($query) use ($startDate, $endDate) {
                                $branchIds = (clone $query)->pluck('id');
                                $q = LoanApplication::whereIn('branch_office_id', $branchIds)->whereIn('sub_status', ['internal_deficiency', 'external_deficiency']);
                                if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                                if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                                return $q->count();
                            })
                    ),
                TextColumn::make('enc_rate')
                    ->label('Enc. Rate')
                    ->alignCenter()
                    ->badge()
                    ->state(function (BranchOffice $record) use ($startDate, $endDate) {
                        $q = $record->loanApplications()->where('approval_status', 'Approved');
                        if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                        if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                        $total = $q->count();
                        $enc = (clone $q)->whereIn('status', ['Encumbered', 'ROD Released', 'Rod Released'])->count();
                        return $total > 0 ? round(($enc / $total) * 100, 1) . '%' : '0%';
                    })
                    ->color(fn ($state) => (float) str_replace('%', '', $state) >= 80 ? 'success' : ((float) str_replace('%', '', $state) > 0 ? 'info' : 'gray'))
                    ->summarize(
                        Summarizer::make()
                            ->label('Overall Rate')
                            ->using(function ($query) use ($startDate, $endDate) {
                                $branchIds = (clone $query)->pluck('id');
                                $q = LoanApplication::whereIn('branch_office_id', $branchIds)->where('approval_status', 'Approved');
                                if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                                if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                                $total = $q->count();
                                $enc = (clone $q)->whereIn('status', ['Encumbered', 'ROD Released', 'Rod Released'])->count();
                                return $total > 0 ? round(($enc / $total) * 100, 1) . '%' : '0%';
                            })
                    ),
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
