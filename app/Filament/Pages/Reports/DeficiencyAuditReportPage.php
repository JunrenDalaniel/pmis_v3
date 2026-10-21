<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Resources\LoanApplicationResource;
use App\Models\BranchOffice;
use App\Models\LoanApplication;
use App\Services\ReportExportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class DeficiencyAuditReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $title = 'Deficiency & Compliance Audit';

    protected static ?string $navigationLabel = 'Deficiency & Audit';

    protected static string | UnitEnum | null $navigationGroup = 'Reports & Analytics';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'reports/deficiency-audit';

    protected string $view = 'filament.pages.reports.report-page';

    public string $report_type = 'deficiency_audit';
    public string $date_preset = 'all';
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?string $branch_office_id = null;
    public ?string $collateral_type = null;
    public ?string $deficiency_type = 'all';

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

    public static function getNavigationBadge(): ?string
    {
        $allowed = auth()->user()?->getAllowedBranchIds();
        $query = LoanApplication::query()
            ->where('approval_status', 'Approved')
            ->whereIn('sub_status', ['internal_deficiency', 'external_deficiency']);
        if (is_array($allowed)) {
            $query->whereIn('branch_office_id', $allowed);
        }

        $count = $query->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
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
        $this->deficiency_type = 'all';
        $this->resetTable();
        $this->generatePreview();
    }

    public function updatedDeficiencyType(): void
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
            'deficiency_type'  => $this->deficiency_type,
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

        if ($this->deficiency_type === 'internal') {
            $query->where('sub_status', 'internal_deficiency');
        } elseif ($this->deficiency_type === 'external') {
            $query->where('sub_status', 'external_deficiency');
        } else {
            $query->whereIn('sub_status', ['internal_deficiency', 'external_deficiency']);
        }

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
            ->striped()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->persistColumnSearchesInSession()
            ->emptyStateHeading('No Audit Deficiencies Found')
            ->emptyStateDescription('All reviewed loan applications comply with credit and documentation guidelines.')
            ->emptyStateIcon('heroicon-o-check-badge')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date Filed')
                    ->date('F d, Y')
                    ->sortable(),

                TextColumn::make('loan_account_no')
                    ->label('Loan Account')
                    ->placeholder('—')
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('Loan account copied to clipboard')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('member.last_name')
                    ->label('Borrower Name')
                    ->formatStateUsing(fn ($state, $record) => $record?->member?->natural_full_name ?? $record?->member?->full_name ?? $state)
                    ->description(fn (LoanApplication $record): ?string => $record->branchOffice?->name)
                    ->sortable(['last_name', 'first_name'])
                    ->searchable(['last_name', 'first_name'])
                    ->weight('semibold'),

                TextColumn::make('collateral_type')
                    ->label('Collateral')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'real_estate_mortgage' => 'REM',
                        'chattel_mortgage'     => 'CM',
                        default                => str($state)->headline(),
                    })
                    ->color(fn ($state) => str_contains((string) $state, 'real') ? 'info' : 'warning'),

                TextColumn::make('requested_amount')
                    ->label('Loan Amount')
                    ->money('PHP')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->summarize(
                        Sum::make()
                            ->money('PHP')
                            ->label('Total Volume')
                    ),

                TextColumn::make('sub_status')
                    ->label('Classification')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'internal_deficiency' => 'Internal Deficiency',
                        'external_deficiency' => 'External Deficiency',
                        default               => str($state)->headline(),
                    })
                    ->color(fn ($state) => match ($state) {
                        'internal_deficiency' => 'warning',
                        'external_deficiency' => 'danger',
                        default               => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Pipeline Stage')
                    ->badge(),

                TextColumn::make('notes')
                    ->label('Assessment Notes')
                    ->state(fn (LoanApplication $record) => $record->notes ?: 'Deficiency noted for compliance')
                    ->limit(60)
                    ->tooltip(fn ($state) => $state),

                TextColumn::make('application_no')
                    ->label('Application ID')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('branchOffice.name')
                    ->label('Branch')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->date('F d, Y')
                    ->sortable()
                    ->label('Last Updated')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('view')
                        ->label('View Loan Application')
                        ->icon('heroicon-m-eye')
                        ->color('gray')
                        ->url(fn (LoanApplication $record) => LoanApplicationResource::getUrl('view', ['record' => $record])),

                    Action::make('update')
                        ->label('Update Deficiency')
                        ->icon('heroicon-m-pencil-square')
                        ->color('warning')
                        ->modalHeading(fn (LoanApplication $record) => "Update Deficiency — {$record->application_no}")
                        ->modalDescription(fn (LoanApplication $record) => "Borrower: " . ($record->member?->full_name ?? '—'))
                        ->modalSubmitActionLabel('Save Changes')
                        ->form([
                            Select::make('sub_status')
                                ->label('Deficiency Classification')
                                ->options([
                                    'internal_deficiency' => 'Internal Deficiency (Branch/Doc requirement)',
                                    'external_deficiency' => 'External Deficiency (ROD/Assessor/LTO/Govt requirement)',
                                    'regular'             => 'Cleared / No Deficiency',
                                ])
                                ->default(fn (LoanApplication $record) => $record->sub_status ?? 'internal_deficiency')
                                ->required(),
                            Textarea::make('notes')
                                ->label('Assessment Notes / Compliance Directives')
                                ->rows(4)
                                ->placeholder('Specify missing requirements, discrepancy details, or instructions...')
                                ->default(fn (LoanApplication $record) => $record->notes),
                        ])
                        ->action(function (LoanApplication $record, array $data): void {
                            // Security: validate sub_status server-side — UI select alone is not enough
                            $validSubStatuses = ['internal_deficiency', 'external_deficiency', 'regular'];
                            $subStatus = in_array($data['sub_status'], $validSubStatuses, true)
                                ? $data['sub_status']
                                : 'internal_deficiency';

                            $record->update([
                                'sub_status' => $subStatus,
                                'notes'      => $data['notes'] ?? $record->notes,
                            ]);

                            $this->generatePreview();

                            Notification::make()
                                ->title('Deficiency Audit Updated')
                                ->body("Application {$record->application_no} deficiency details updated successfully.")
                                ->success()
                                ->send();
                        }),

                    Action::make('print')
                        ->label('Print Deficiency Slip')
                        ->icon('heroicon-m-printer')
                        ->color('success')
                        ->action(function (LoanApplication $record) {
                            $url = route('reports.deficiency-slip', $record) . '?autoprint=1';
                            $this->dispatch('print-report-silent', url: $url);
                        }),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('Actions'),
            ])
            ->defaultPaginationPageOption(10);
    }

    public function getDeficiencyCounts(): array
    {
        [$startDate, $endDate] = $this->getDateRange();
        $allowed = auth()->user()?->getAllowedBranchIds();

        $query = LoanApplication::query()
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

        $records = $query->get();
        return [
            'all'      => $records->count(),
            'internal' => $records->where('sub_status', 'internal_deficiency')->count(),
            'external' => $records->where('sub_status', 'external_deficiency')->count(),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'branches'             => $this->branches,
            'printUrl'             => $this->getPrintUrl(),
            'excelUrl'             => $this->getExcelUrl(),
            'hasCollateralFilter'  => true,
            'hasDeficiencyFilter'  => true,
            'deficiency_type'      => $this->deficiency_type,
            'deficiencyCounts'     => $this->getDeficiencyCounts(),
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
            'deficiency_type'  => $this->deficiency_type,
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
            'deficiency_type'  => $this->deficiency_type,
        ]);

        return route('reports.export-excel') . '?' . $params;
    }
}
