<?php

namespace App\Filament\Pages;

use App\Models\BranchOffice;
use App\Models\ChattelCollateral;
use App\Models\LoanApplication;
use App\Services\ReportExportService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReportsCenterPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $title = 'Reports Center';

    protected static string | \UnitEnum | null $navigationGroup = 'Reports & Analytics';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.reports-center-page';

    public string $report_type = 'loan_release';
    public string $date_preset = 'all';
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?string $branch_office_id = null;
    public ?string $collateral_type = null;

    public ?array $previewData = null;

    public function getReportTypes(): array
    {
        return [
            'loan_release' => [
                'label' => 'Loan Release',
                'badge' => 'Portfolio',
                'icon' => 'heroicon-o-document-currency-dollar',
                'desc' => 'Released & approved loan portfolio',
            ],
            'branch_consolidated' => [
                'label' => 'Consolidated Branches',
                'badge' => 'Executive Matrix',
                'icon' => 'heroicon-o-building-office-2',
                'desc' => 'Cross-branch volume & encumbrance',
            ],
            'pipeline_aging' => [
                'label' => 'Pipeline & Aging',
                'badge' => 'Turnaround',
                'icon' => 'heroicon-o-clock',
                'desc' => 'Document stages & processing delay',
            ],
            'collateral_inventory' => [
                'label' => 'Collateral Inventory',
                'badge' => 'Securities',
                'icon' => 'heroicon-o-shield-check',
                'desc' => 'Custody, RD registration & encumbrance',
            ],
            'lto_renewal' => [
                'label' => 'Vehicle LTO Due',
                'badge' => 'Compliance',
                'icon' => 'heroicon-o-truck',
                'desc' => 'Chattel registration expiration alert',
            ],
            'deficiency_audit' => [
                'label' => 'Deficiency & Audit',
                'badge' => 'Findings',
                'icon' => 'heroicon-o-exclamation-triangle',
                'desc' => 'Application notes & compliance matters',
            ],
            'rod_pending_status' => [
                'label' => 'ROD Pending Status',
                'badge' => 'EPEB',
                'icon' => 'heroicon-o-building-library',
                'desc' => 'Registry of Deeds EPEB tracking & release monitoring',
            ],
        ];
    }

    public function setReportType(string $type): void
    {
        $this->report_type = $type;
        $this->resetTable();
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

    public function mount(): void
    {
        $this->generatePreview();
    }

    public function updatedReportType(): void
    {
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
        return match ($this->report_type) {
            'branch_consolidated'  => $this->buildBranchConsolidatedTable($table),
            'pipeline_aging'       => $this->buildPipelineAgingTable($table),
            'collateral_inventory' => $this->buildCollateralInventoryTable($table),
            'lto_renewal'          => $this->buildLtoRenewalTable($table),
            'deficiency_audit'     => $this->buildDeficiencyAuditTable($table),
            'rod_pending_status'   => $this->buildRodPendingTable($table),
            default                => $this->buildLoanReleaseTable($table),
        };
    }

    /**
     * 1. Loan Release & Portfolio Summary Table
     */
    protected function buildLoanReleaseTable(Table $table): Table
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
            ->columns([
                TextColumn::make('application_no')
                    ->label('Application ID')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->color('primary'),
                TextColumn::make('created_at')
                    ->date('Y-m-d')
                    ->sortable()
                    ->label('Date Filed'),
                TextColumn::make('member.full_name')
                    ->label('Borrower Name')
                    ->sortable(['last_name', 'first_name'])
                    ->searchable(['last_name', 'first_name']),
                TextColumn::make('member.member_no')
                    ->label('Member No.')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('branchOffice.name')
                    ->label('OIC Branch')
                    ->sortable(),
                TextColumn::make('collateral_type')
                    ->label('Collateral')
                    ->badge()
                    ->formatStateUsing(fn ($state) => strtoupper(str_replace('_', ' ', (string) $state)))
                    ->color(fn ($state) => str_contains((string) $state, 'real') ? 'info' : 'warning'),
                TextColumn::make('requested_amount')
                    ->label('Loan Amount')
                    ->money('PHP')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success'),
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

    /**
     * 2. Consolidated Branches Executive Matrix Table
     */
    protected function buildBranchConsolidatedTable(Table $table): Table
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
            ->columns([
                TextColumn::make('name')
                    ->label('OIC Branch')
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('cm_count')
                    ->label('Chattel Mortgage (CM)')
                    ->alignCenter()
                    ->state(function (BranchOffice $record) use ($startDate, $endDate) {
                        $q = $record->loanApplications()->where('approval_status', 'Approved')->where(fn ($sq) => $sq->where('collateral_type', 'like', '%chattel%')->orWhere('collateral_type', 'like', '%cm%'));
                        if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                        if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                        return $q->count();
                    }),
                TextColumn::make('rem_count')
                    ->label('Real Estate Mortgage (REM)')
                    ->alignCenter()
                    ->state(function (BranchOffice $record) use ($startDate, $endDate) {
                        $q = $record->loanApplications()->where('approval_status', 'Approved')->where(fn ($sq) => $sq->where('collateral_type', 'like', '%real%')->orWhere('collateral_type', 'like', '%rem%'));
                        if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                        if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                        return $q->count();
                    }),
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
                    }),
                TextColumn::make('enc_cm')
                    ->label('ENCUMBERED CM')
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->state(function (BranchOffice $record) use ($startDate, $endDate) {
                        $q = $record->loanApplications()->where('approval_status', 'Approved')->where(fn ($sq) => $sq->where('collateral_type', 'like', '%chattel%')->orWhere('collateral_type', 'like', '%cm%'))->whereIn('status', ['Encumbered', 'ROD Released', 'Rod Released']);
                        if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                        if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                        return $q->count();
                    }),
                TextColumn::make('enc_rem')
                    ->label('ENCUMBERED REM')
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->state(function (BranchOffice $record) use ($startDate, $endDate) {
                        $q = $record->loanApplications()->where('approval_status', 'Approved')->where(fn ($sq) => $sq->where('collateral_type', 'like', '%real%')->orWhere('collateral_type', 'like', '%rem%'))->whereIn('status', ['Encumbered', 'ROD Released', 'Rod Released']);
                        if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                        if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                        return $q->count();
                    }),
                TextColumn::make('on_process')
                    ->label('ON PROCESS')
                    ->alignCenter()
                    ->badge()
                    ->color('warning')
                    ->state(function (BranchOffice $record) use ($startDate, $endDate) {
                        $q = $record->loanApplications()->where('approval_status', 'Approved')->whereNotIn('status', ['Encumbered', 'ROD Released', 'Rod Released']);
                        if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                        if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                        return $q->count();
                    }),
                TextColumn::make('deficiency')
                    ->label('Deficiency')
                    ->alignCenter()
                    ->badge()
                    ->color('danger')
                    ->state(function (BranchOffice $record) use ($startDate, $endDate) {
                        $q = $record->loanApplications()
                            ->where('approval_status', 'Approved')
                            ->whereIn('sub_status', ['internal_deficiency', 'external_deficiency']);
                        if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                        if ($endDate) $q->whereDate('created_at', '<=', $endDate);
                        return $q->count();
                    }),
                TextColumn::make('enc_rate')
                    ->label('Encumbrance Rate')
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
                    ->color(fn ($state) => (float) str_replace('%', '', $state) >= 80 ? 'success' : ((float) str_replace('%', '', $state) > 0 ? 'info' : 'gray')),
            ])
            ->defaultPaginationPageOption(10);
    }

    /**
     * 3. Document Pipeline & Aging Turnaround Table
     */
    protected function buildPipelineAgingTable(Table $table): Table
    {
        [$startDate, $endDate] = $this->getDateRange();
        $allowed = auth()->user()?->getAllowedBranchIds();

        $query = LoanApplication::query()
            ->with(['member', 'branchOffice'])
            ->onProcess();

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
            ->columns([
                TextColumn::make('application_no')
                    ->label('Application ID')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->color('primary'),
                TextColumn::make('member.full_name')
                    ->label('Borrower Name')
                    ->sortable(['last_name', 'first_name'])
                    ->searchable(['last_name', 'first_name']),
                TextColumn::make('branchOffice.name')
                    ->label('OIC Branch')
                    ->sortable(),
                TextColumn::make('collateral_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => strtoupper(str_replace('_', ' ', (string) $state))),
                TextColumn::make('requested_amount')
                    ->label('Amount')
                    ->money('PHP')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold'),
                TextColumn::make('status')
                    ->label('Current Stage')
                    ->badge(),
                TextColumn::make('aging_days')
                    ->label('Days in Stage')
                    ->badge()
                    ->state(function (LoanApplication $record) {
                        $date = $record->status_changed_at ?? $record->updated_at ?? $record->created_at;
                        return $date ? abs((int) now()->diffInDays($date)) . ' days' : '0 days';
                    })
                    ->color(function (LoanApplication $record) {
                        $date = $record->status_changed_at ?? $record->updated_at ?? $record->created_at;
                        $days = $date ? abs((int) now()->diffInDays($date)) : 0;
                        return $days > 30 ? 'danger' : ($days > 15 ? 'warning' : 'success');
                    }),
                TextColumn::make('turnaround_status')
                    ->label('Turnaround')
                    ->badge()
                    ->state(function (LoanApplication $record) {
                        $date = $record->status_changed_at ?? $record->updated_at ?? $record->created_at;
                        $days = $date ? abs((int) now()->diffInDays($date)) : 0;
                        return $days > 30 ? 'DELAYED' : ($days > 15 ? 'DUE SOON' : 'ON TRACK');
                    })
                    ->color(function (LoanApplication $record) {
                        $date = $record->status_changed_at ?? $record->updated_at ?? $record->created_at;
                        $days = $date ? abs((int) now()->diffInDays($date)) : 0;
                        return $days > 30 ? 'danger' : ($days > 15 ? 'warning' : 'success');
                    }),
                TextColumn::make('updated_at')
                    ->date('Y-m-d')
                    ->sortable()
                    ->label('Last Movement'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (LoanApplication $record) => route('filament.admin.resources.loan-applications.view', $record)),
            ])
            ->defaultPaginationPageOption(10);
    }

    /**
     * 4. Collateral & Encumbrance Inventory Table
     */
    protected function buildCollateralInventoryTable(Table $table): Table
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
            ->columns([
                TextColumn::make('application_no')
                    ->label('App ID')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->color('primary'),
                TextColumn::make('member.full_name')
                    ->label('Borrower Name')
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
                    ->weight('bold'),
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

    /**
     * 5. Vehicle LTO Due Table
     */
    protected function buildLtoRenewalTable(Table $table): Table
    {
        $allowed = auth()->user()?->getAllowedBranchIds();

        $query = ChattelCollateral::query()
            ->with(['loanApplication.member', 'loanApplication.branchOffice'])
            ->whereHas('loanApplication', function ($lq) use ($allowed) {
                if (is_array($allowed)) {
                    $lq->whereIn('branch_office_id', $allowed);
                }
                if (filled($this->branch_office_id)) {
                    $lq->where('branch_office_id', $this->branch_office_id);
                }
            });

        return $table
            ->query($query->orderBy('registration_renewal_date'))
            ->columns([
                TextColumn::make('plate_no')
                    ->label('Plate No.')
                    ->formatStateUsing(fn ($state, $record) => $state ?: ($record->mv_file_no ?: 'No Plate'))
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->color('primary'),
                TextColumn::make('vehicle_description')
                    ->label('Vehicle Description')
                    ->state(fn (ChattelCollateral $record) => ($record->year_model ? "{$record->year_model} " : '') . ($record->vehicle_description ?: 'Vehicle')),
                TextColumn::make('engine_no')
                    ->label('Engine / Chassis')
                    ->state(fn (ChattelCollateral $record) => ($record->engine_no ?: 'N/A') . ' / ' . ($record->chassis_no ?: 'N/A')),
                TextColumn::make('loanApplication.member.full_name')
                    ->label('Borrower Name')
                    ->formatStateUsing(fn ($state, $record) => $record?->loanApplication?->member?->natural_full_name ?? $record?->loanApplication?->member?->full_name ?? $state)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('loanApplication.member', function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            \App\Models\Member::select('last_name')
                                ->join('loan_applications', 'loan_applications.member_id', '=', 'members.id')
                                ->whereColumn('loan_applications.id', 'chattel_collaterals.loan_application_id')
                                ->limit(1),
                            $direction
                        );
                    }),
                TextColumn::make('loanApplication.branchOffice.name')
                    ->label('Branch')
                    ->sortable(),
                TextColumn::make('registration_renewal_date')
                    ->date('Y-m-d')
                    ->sortable()
                    ->label('LTO Renewal Date'),
                TextColumn::make('days_left')
                    ->label('Days Left / Expired')
                    ->badge()
                    ->state(function (ChattelCollateral $record) {
                        $date = $record->registration_renewal_date;
                        if (!$date) return 'None';
                        $c = Carbon::parse($date);
                        if ($c->isPast()) {
                            return 'Expired ' . abs((int) now()->diffInDays($c)) . 'd ago';
                        }
                        return (int) now()->diffInDays($c) . ' days left';
                    })
                    ->color(function (ChattelCollateral $record) {
                        $date = $record->registration_renewal_date;
                        if (!$date) return 'gray';
                        $c = Carbon::parse($date);
                        return $c->isPast() ? 'danger' : ((int) now()->diffInDays($c) <= 30 ? 'warning' : 'success');
                    }),
                TextColumn::make('lto_status')
                    ->label('Status')
                    ->badge()
                    ->state(function (ChattelCollateral $record) {
                        $date = $record->registration_renewal_date;
                        if (!$date) return 'NO DATE';
                        $c = Carbon::parse($date);
                        return $c->isPast() ? 'EXPIRED' : ((int) now()->diffInDays($c) <= 30 ? 'DUE SOON' : 'ACTIVE');
                    })
                    ->color(function (ChattelCollateral $record) {
                        $date = $record->registration_renewal_date;
                        if (!$date) return 'gray';
                        $c = Carbon::parse($date);
                        return $c->isPast() ? 'danger' : ((int) now()->diffInDays($c) <= 30 ? 'warning' : 'success');
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View Loan')
                    ->icon('heroicon-o-eye')
                    ->url(fn (ChattelCollateral $record) => $record->loanApplication ? route('filament.admin.resources.loan-applications.view', $record->loanApplication) : '#'),
            ])
            ->defaultPaginationPageOption(10);
    }

    /**
     * 6. Deficiency & Compliance Audit Table
     */
    protected function buildDeficiencyAuditTable(Table $table): Table
    {
        [$startDate, $endDate] = $this->getDateRange();
        $allowed = auth()->user()?->getAllowedBranchIds();

        $query = LoanApplication::query()
            ->with(['member', 'branchOffice'])
            ->where('approval_status', 'Approved')
            ->whereIn('sub_status', ['internal_deficiency', 'external_deficiency']);

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
            ->query($query->orderByDesc('updated_at'))
            ->columns([
                TextColumn::make('application_no')
                    ->label('Application ID')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->color('primary'),
                TextColumn::make('member.full_name')
                    ->label('Borrower Name')
                    ->sortable(['last_name', 'first_name'])
                    ->searchable(['last_name', 'first_name']),
                TextColumn::make('branchOffice.name')
                    ->label('Branch Office')
                    ->sortable(),
                TextColumn::make('collateral_type')
                    ->label('Collateral')
                    ->badge(),
                TextColumn::make('requested_amount')
                    ->label('Loan Amount')
                    ->money('PHP')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold'),
                TextColumn::make('sub_status')
                    ->label('Deficiency Classification')
                    ->badge()
                    ->formatStateUsing(fn ($state) => strtoupper(str_replace('_', ' ', (string) $state)))
                    ->color('danger'),
                TextColumn::make('status')
                    ->label('Pipeline Stage')
                    ->badge(),
                TextColumn::make('notes')
                    ->label('Assessment Notes / Remarks')
                    ->state(fn (LoanApplication $record) => $record->notes ?: ($record->internal_deficiency_remarks ?: ($record->external_deficiency_remarks ?: 'Deficiency noted for compliance')))
                    ->limit(40),
                TextColumn::make('updated_at')
                    ->date('Y-m-d')
                    ->sortable()
                    ->label('Last Updated'),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (LoanApplication $record) => route('filament.admin.resources.loan-applications.view', $record)),
            ])
            ->defaultPaginationPageOption(10);
    }

    /**
     * 7. ROD Pending Status Table
     */
    protected function buildRodPendingTable(Table $table): Table
    {
        [$startDate, $endDate] = $this->getDateRange();
        $allowed = auth()->user()?->getAllowedBranchIds();

        $query = LoanApplication::query()
            ->with(['member', 'branchOffice', 'currentRodBranch', 'realEstateCollaterals'])
            ->whereIn('collateral_type', ['real_estate_mortgage', 'rem_amendment', 'rem_certification'])
            ->whereIn('status', ['ROD Paid', 'Rod Paid', 'ROD Released', 'Rod Released']);

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

        return $table
            ->query($query->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('application_no')
                    ->label('Application ID')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->color('primary'),
                TextColumn::make('member.full_name')
                    ->label('Borrower Name')
                    ->sortable(['last_name', 'first_name'])
                    ->searchable(['last_name', 'first_name']),
                TextColumn::make('branchOffice.name')
                    ->label('OIC Branch')
                    ->sortable(),
                TextColumn::make('currentRodBranch.name')
                    ->label('ROD Office')
                    ->state(fn (LoanApplication $record) => $record->currentRodBranch?->name ?? 'N/A')
                    ->sortable(),
                TextColumn::make('rod_entry_no')
                    ->label('EPEB Entry #')
                    ->searchable()
                    ->copyable()
                    ->color('info')
                    ->weight('bold'),
                TextColumn::make('title_number')
                    ->label('Title No.')
                    ->state(fn (LoanApplication $record) => $record->realEstateCollaterals->first()?->title_number ?? 'N/A'),
                TextColumn::make('rod_payment_date')
                    ->date('Y-m-d')
                    ->sortable()
                    ->label('Payment Date'),
                TextColumn::make('rod_fees_amount')
                    ->money('PHP')
                    ->sortable()
                    ->alignEnd()
                    ->label('ROD Amount Paid'),
                TextColumn::make('status')
                    ->badge()
                    ->label('ROD Status')
                    ->color(fn ($state) => str_contains(strtolower((string) $state), 'release') ? 'success' : 'warning'),
            ])
            ->recordActions([
                Action::make('verifyLots')
                    ->label('Verify LOTS ↗')
                    ->icon('heroicon-o-globe-alt')
                    ->color('info')
                    ->url('https://lots.lra.gov.ph/TransactionStatus/')
                    ->openUrlInNewTab(),
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
            'reportTypes' => $this->getReportTypes(),
            'branches'    => $this->branches,
            'printUrl'    => $this->getPrintUrl(),
            'excelUrl'    => $this->getExcelUrl(),
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

