<?php

namespace App\Filament\Pages;

use App\Filament\Resources\LoanApplicationResource;
use App\Models\BranchOffice;
use App\Models\LoanApplication;
use App\Models\RodBranch;
use App\Models\RodStatus;
use App\Services\ReportExportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RodPendingStatusPage extends Page implements HasTable
{
    use InteractsWithTable;
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $title = 'EPEB Status Report';

    protected static ?string $navigationLabel = 'ROD Pending Status';

    protected static string | UnitEnum | null $navigationGroup = 'Reports & Analytics';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'rod-pending-status';

    protected string $view = 'filament.pages.rod-pending-status-page';

    public ?string $rod_branch_id = '';
    public ?string $branch_office_id = '';
    public ?string $collateral_type = '';
    public ?string $year = '';
    public ?string $rod_status_id = '';
    public string $status_filter = 'all';
    public ?string $search = '';

    public ?array $reportData = null;

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $allowed = $user?->getAllowedBranchIds();
        $query = LoanApplication::query()
            ->whereIn('collateral_type', ['real_estate_mortgage', 'rem_amendment', 'rem_certification'])
            ->whereIn('status', ['ROD Paid', 'Rod Paid'])
            ->whereNull('rod_release_date');

        if (is_array($allowed)) {
            $query->whereIn('branch_office_id', $allowed);
        }

        $count = $query->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('checkLraLots')
                ->label('Verify at LRA LOTS Online')
                ->icon('heroicon-o-globe-alt')
                ->color('info')
                ->url('https://lots.lra.gov.ph/TransactionStatus/')
                ->openUrlInNewTab(),
            \Filament\Actions\Action::make('printReport')
                ->label('Print Report')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(function () {
                    $this->dispatch('print-report-silent', url: $this->getPrintUrl() . '&autoprint=1');
                }),
            \Filament\Actions\Action::make('exportExcel')
                ->label('Export to Excel (.xlsx)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->action(function () {
                    $filters = [
                        'rod_branch_id'    => $this->rod_branch_id,
                        'branch_office_id' => $this->branch_office_id,
                        'collateral_type'  => $this->collateral_type,
                        'year'             => $this->year,
                        'rod_status_id'    => $this->rod_status_id,
                        'status_filter'    => $this->status_filter,
                        'search'           => $this->search,
                    ];

                    return ReportExportService::exportToExcel('rod_pending_status', $filters, auth()->user());
                }),
        ];
    }

    public function boot(): void
    {
        try {
            $key = $this->getTableSortSessionKey();
            if (str_contains((string) session()->get($key), 'member.full_name')) {
                session()->forget($key);
            }
        } catch (\Throwable $e) {
            // safe fallback
        }

        if (str_contains((string) $this->tableSort, 'member.full_name')) {
            $this->tableSort = null;
        }
    }

    public function mount(): void
    {
        try {
            $key = $this->getTableSortSessionKey();
            if (str_contains((string) session()->get($key), 'member.full_name')) {
                session()->forget($key);
            }
        } catch (\Throwable $e) {
            // safe fallback
        }

        if (str_contains((string) $this->tableSort, 'member.full_name')) {
            $this->tableSort = null;
        }

        $this->generateReport();
    }

    public function updated($propertyName): void
    {
        $this->resetTable();
        $this->generateReport();
    }

    public function resetFilters(): void
    {
        $this->rod_branch_id = '';
        $this->branch_office_id = '';
        $this->collateral_type = '';
        $this->year = '';
        $this->rod_status_id = '';
        $this->status_filter = 'all';
        $this->search = '';
        $this->resetTable();
        $this->generateReport();
    }

    public function setStatusFilter(string $status): void
    {
        $this->status_filter = $status;
        $this->resetTable();
        $this->generateReport();
    }

    public function generateReport(): void
    {
        $filters = [
            'rod_branch_id'    => $this->rod_branch_id,
            'branch_office_id' => $this->branch_office_id,
            'collateral_type'  => $this->collateral_type,
            'year'             => $this->year,
            'rod_status_id'    => $this->rod_status_id,
            'status_filter'    => $this->status_filter,
            'search'           => $this->search,
        ];

        $this->reportData = ReportExportService::getRodPendingStatusReport($filters, auth()->user()?->getAllowedBranchIds(), [
            'generated_at' => now()->format('F d, Y h:i A'),
            'generated_by' => auth()->user()?->name ?? 'System User',
        ]);
    }

    protected function getTableQuery(): Builder
    {
        $allowed = auth()->user()?->getAllowedBranchIds();

        $query = LoanApplication::query()
            ->with(['member', 'branchOffice', 'currentRodBranch', 'currentRodStatus', 'realEstateCollaterals.rodBranch'])
            ->whereIn('collateral_type', ['real_estate_mortgage', 'rem_amendment', 'rem_certification']);

        if (is_array($allowed)) {
            $query->whereIn('branch_office_id', $allowed);
        }

        if (filled($this->branch_office_id)) {
            $query->where('branch_office_id', $this->branch_office_id);
        }

        if (filled($this->rod_branch_id)) {
            $query->where(function ($q) {
                $q->where('current_rod_branch_id', $this->rod_branch_id)
                  ->orWhereHas('realEstateCollaterals', fn ($sq) => $sq->where('rod_branch_id', $this->rod_branch_id));
            });
        }

        if (filled($this->collateral_type)) {
            $query->where('collateral_type', $this->collateral_type);
        }

        if (filled($this->year)) {
            $query->where(function ($q) {
                $q->whereYear('rod_payment_date', $this->year)
                  ->orWhereYear('date_granted', $this->year)
                  ->orWhereYear('created_at', $this->year);
            });
        }

        if (filled($this->rod_status_id)) {
            $query->where('current_rod_status_id', $this->rod_status_id);
        }

        if ($this->status_filter === 'pending') {
            $query->whereIn('status', ['ROD Paid', 'Rod Paid'])
                  ->whereNull('rod_release_date');
        } elseif ($this->status_filter === 'released') {
            $query->where(function ($q) {
                $q->whereIn('status', ['ROD Released', 'Rod Released'])
                  ->orWhere(function ($sq) {
                      $sq->whereIn('status', ['ROD Paid', 'Rod Paid'])
                         ->whereNotNull('rod_release_date');
                  });
            });
        } else {
            $query->where(function ($q) {
                $q->whereIn('status', ['ROD Paid', 'Rod Paid', 'ROD Released', 'Rod Released'])
                  ->orWhere(function ($sq) {
                      $sq->whereNotNull('rod_payment_date')
                         ->whereNotIn('status', ['Initial Assessment', 'For Notary', 'Notarized', 'For BIR', 'BIR Paid', 'For ROD', 'Encumbered', 'Fully Paid', 'Deceased']);
                  });
            });
        }

        if (filled($this->search)) {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->where('application_no', 'like', "%{$term}%")
                  ->orWhere('rod_entry_no', 'like', "%{$term}%")
                  ->orWhere('rod_release_entry_no', 'like', "%{$term}%")
                  ->orWhereHas('member', function ($mq) use ($term) {
                      $mq->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('member_no', 'like', "%{$term}%")
                        ->orWhere('cid', 'like', "%{$term}%");
                  })
                  ->orWhereHas('realEstateCollaterals', function ($rq) use ($term) {
                      $rq->where('title_number', 'like', "%{$term}%");
                  });
            });
        }

        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->striped()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->persistColumnSearchesInSession()
            ->emptyStateHeading('No EPEB Records Found')
            ->emptyStateDescription('Try adjusting your ROD Branch, OIC Branch, or Status filter tabs.')
            ->emptyStateIcon('heroicon-o-building-library')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date Filed')
                    ->date('F d, Y')
                    ->sortable(),

                TextColumn::make('member.last_name')
                    ->label('Borrower Name')
                    ->formatStateUsing(fn ($state, $record) => $record?->member?->natural_full_name ?? $record?->member?->full_name ?? $state)
                    ->description(fn ($record) => $record->branchOffice?->name)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('member', function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%");
                        })->orWhere('application_no', 'like', "%{$search}%");
                    })
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('requested_amount')
                    ->label('Loan Amount')
                    ->money('PHP')
                    ->sortable(),

                TextColumn::make('collateral_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'real_estate_mortgage' => 'REM',
                        'rem_amendment'        => 'REM Amendment',
                        'rem_certification'    => 'REM Cert',
                        default                => ucwords(str_replace('_', ' ', (string) $state)),
                    })
                    ->color('primary'),

                TextColumn::make('branchOffice.name')
                    ->label('OIC Branch')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('rod_office')
                    ->label('ROD Office')
                    ->getStateUsing(fn ($record) => $record->currentRodBranch?->name ?? $record->realEstateCollaterals->first()?->rodBranch?->name ?? '—')
                    ->weight('medium'),

                TextColumn::make('rod_entry_no')
                    ->label('EPEB Entry #')
                    ->formatStateUsing(fn ($state) => $state ?: 'Pending Entry')
                    ->copyable()
                    ->copyMessage('EPEB Entry Number copied to clipboard')
                    ->tooltip(fn ($state) => $state && $state !== 'Pending Entry' ? 'Click to copy EPEB #' : null)
                    ->searchable()
                    ->weight('bold')
                    ->color(fn ($state) => $state && $state !== 'Pending Entry' ? 'info' : 'gray'),

                TextColumn::make('title_no')
                    ->label('Title No.')
                    ->getStateUsing(fn ($record) => $record->realEstateCollaterals->pluck('title_number')->filter()->implode(', ') ?: '—'),

                TextColumn::make('current_rod_status')
                    ->label('ROD Status')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        if ($record->currentRodStatus?->name) {
                            return $record->currentRodStatus->name;
                        }
                        if (filled($record->rod_release_date) || in_array($record->status, ['ROD Released', 'Encumbered'])) {
                            return 'Releasing';
                        }
                        if (filled($record->rod_payment_date) || $record->status === 'ROD Paid') {
                            return filled($record->rod_entry_no) ? 'Encoding' : 'Payment';
                        }
                        return 'Entry';
                    })
                    ->color(fn ($state) => match ($state) {
                        'Closed'                      => 'gray',
                        'Releasing', 'Approval'       => 'success',
                        'Printing', 'Scan/Uploading'  => 'info',
                        'Examination', 'Encoding'     => 'warning',
                        'Payment'                     => 'primary',
                        'Entry'                       => 'gray',
                        default                       => 'gray',
                    }),

                TextColumn::make('pending_days')
                    ->label('Pending Days')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        if (filled($record->rod_release_date) || in_array($record->status, ['ROD Released', 'Encumbered'])) {
                            return 'Released';
                        }
                        $baseDate = $record->rod_payment_date ?? ($record->status_changed_at ?? $record->created_at);
                        $days = abs((int) now()->diffInDays($baseDate));
                        return $days === 0 ? 'Today' : "{$days} day(s)";
                    })
                    ->color(fn ($state) => match (true) {
                        $state === 'Released' => 'success',
                        str_contains((string) $state, 'day') && ((int) filter_var($state, FILTER_SANITIZE_NUMBER_INT)) > 30 => 'danger',
                        str_contains((string) $state, 'day') && ((int) filter_var($state, FILTER_SANITIZE_NUMBER_INT)) >= 15 => 'warning',
                        default => 'gray',
                    }),

                // Optional Toggleable Columns (Hidden by default to keep table compact)

                TextColumn::make('application_no')
                    ->label('Application ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('member_info')
                    ->label('Member ID / CID')
                    ->getStateUsing(fn ($record) => ($record->member?->member_no ?? '—') . ($record->member?->cid ? " [{$record->member->cid}]" : ''))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('rod_payment_date')
                    ->label('Date of Payment')
                    ->date('F d, Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('rod_amount_paid')
                    ->label('ROD Amount Paid')
                    ->money('PHP')
                    ->placeholder('—')
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('rod_release_entry_no')
                    ->label('Release Entry #')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('rod_release_date')
                    ->label('Release Date')
                    ->date('F d, Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('updateRodStatus')
                        ->label('Update Status')
                        ->icon('heroicon-m-pencil-square')
                        ->color('warning')
                        ->modalHeading(fn (LoanApplication $record) => "Update ROD Status — {$record->application_no}")
                        ->modalDescription(fn (LoanApplication $record) => "Borrower: {$record->member?->full_name} | EPEB Ref: " . ($record->rod_entry_no ?: 'Pending Entry'))
                        ->modalSubmitActionLabel('Save Changes')
                        ->form([
                            Grid::make(2)->schema([
                                Select::make('current_rod_status_id')
                                    ->label('ROD Workflow Stage')
                                    ->options(RodStatus::query()->where('is_active', true)->orderBy('step_number')->pluck('name', 'id'))
                                    ->default(fn (LoanApplication $record) => $record->current_rod_status_id)
                                    ->placeholder('Select current stage')
                                    ->required(),
                                TextInput::make('rod_entry_no')
                                    ->label('EPEB / Ref Entry #')
                                    ->placeholder('e.g. 2026001432')
                                    ->default(fn (LoanApplication $record) => $record->rod_entry_no),
                            ]),
                            Grid::make(2)->schema([
                                DatePicker::make('rod_payment_date')
                                    ->label('Date of Payment')
                                    ->default(fn (LoanApplication $record) => $record->rod_payment_date),
                                TextInput::make('rod_amount_paid')
                                    ->label('ROD Amount Paid (₱)')
                                    ->numeric()
                                    ->prefix('₱')
                                    ->default(fn (LoanApplication $record) => $record->rod_amount_paid),
                            ]),
                            Grid::make(2)->schema([
                                TextInput::make('rod_release_entry_no')
                                    ->label('Release Entry # (if Released)')
                                    ->placeholder('e.g. 2026-0005678')
                                    ->default(fn (LoanApplication $record) => $record->rod_release_entry_no),
                                DatePicker::make('rod_release_date')
                                    ->label('Release Date (if Released)')
                                    ->default(fn (LoanApplication $record) => $record->rod_release_date),
                            ]),
                        ])
                        ->action(function (LoanApplication $record, array $data): void {
                            $updateData = [
                                'current_rod_status_id' => $data['current_rod_status_id'] ?? null,
                                'rod_entry_no'          => $data['rod_entry_no'] ?? null,
                                'rod_payment_date'      => $data['rod_payment_date'] ?? null,
                                'rod_amount_paid'       => $data['rod_amount_paid'] ?? null,
                                'rod_release_entry_no'  => $data['rod_release_entry_no'] ?? null,
                                'rod_release_date'      => $data['rod_release_date'] ?? null,
                            ];

                            $rodStatus = RodStatus::find($data['current_rod_status_id'] ?? null);
                            if ($rodStatus && in_array($rodStatus->name, ['Releasing', 'Closed'])) {
                                if (blank($record->rod_release_date) && filled($data['rod_release_date'])) {
                                    $updateData['rod_release_date'] = $data['rod_release_date'];
                                } elseif (blank($record->rod_release_date)) {
                                    $updateData['rod_release_date'] = now();
                                }
                            }

                            $record->update($updateData);

                            Notification::make()
                                ->title('ROD Status Updated')
                                ->body("Application {$record->application_no} updated to " . ($rodStatus?->name ?? 'selected stage') . '.')
                                ->success()
                                ->send();
                        }),
                    Action::make('checkLots')
                        ->label('Verify LOTS')
                        ->icon('heroicon-m-arrow-top-right-on-square')
                        ->color('info')
                        ->url('https://lots.lra.gov.ph/TransactionStatus/')
                        ->openUrlInNewTab(),
                    Action::make('view')
                        ->label('View Loan Application')
                        ->icon('heroicon-m-eye')
                        ->color('gray')
                        ->url(fn ($record) => LoanApplicationResource::getUrl('view', ['record' => $record])),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('Actions'),
            ])
            ->emptyStateHeading('No Registry of Deeds (ROD) records found')
            ->emptyStateDescription('Try adjusting the ROD Branch, OIC Branch, Type, or Year filters.')
            ->emptyStateIcon('heroicon-o-building-library')
            ->defaultSort('id', 'desc');
    }

    public function getRodBranchesProperty(): array
    {
        return RodBranch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function getOicBranchesProperty(): array
    {
        $user = auth()->user();
        $allowed = $user?->getAllowedBranchIds();
        $q = BranchOffice::query()->where('is_active', true)->orderBy('name');
        if (is_array($allowed)) {
            $q->whereIn('id', $allowed);
        }
        return $q->pluck('name', 'id')->toArray();
    }

    public function getYearsProperty(): array
    {
        $currentYear = (int) now()->year;
        $years = [];
        for ($y = $currentYear; $y >= 2020; $y--) {
            $years[(string) $y] = (string) $y;
        }
        return $years;
    }

    public function getRodStatusesProperty(): array
    {
        return RodStatus::query()
            ->where('is_active', true)
            ->orderBy('step_number')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function getPrintUrl(): string
    {
        $params = http_build_query([
            'report_type'      => 'rod_pending_status',
            'rod_branch_id'    => $this->rod_branch_id,
            'branch_office_id' => $this->branch_office_id,
            'collateral_type'  => $this->collateral_type,
            'year'             => $this->year,
            'rod_status_id'    => $this->rod_status_id,
            'status_filter'    => $this->status_filter,
            'search'           => $this->search,
        ]);

        return route('reports.print') . '?' . $params;
    }

    public function getExcelUrl(): string
    {
        $params = http_build_query([
            'report_type'      => 'rod_pending_status',
            'rod_branch_id'    => $this->rod_branch_id,
            'branch_office_id' => $this->branch_office_id,
            'collateral_type'  => $this->collateral_type,
            'year'             => $this->year,
            'rod_status_id'    => $this->rod_status_id,
            'status_filter'    => $this->status_filter,
            'search'           => $this->search,
        ]);

        return route('reports.export-excel') . '?' . $params;
    }

    protected function getViewData(): array
    {
        return [
            'rodBranches' => $this->rodBranches,
            'oicBranches' => $this->oicBranches,
            'years'       => $this->years,
            'rodStatuses' => $this->rodStatuses,
            'printUrl'    => $this->getPrintUrl(),
            'excelUrl'    => $this->getExcelUrl(),
        ];
    }
}
