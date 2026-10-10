<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Resources\LoanApplicationResource;
use App\Models\BranchOffice;
use App\Models\ChattelCollateral;
use App\Services\ReportExportService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class VehicleLtoDueReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $title = 'Vehicle LTO Registration & Renewal Due';

    protected static ?string $navigationLabel = 'Vehicle LTO Due';

    protected static string | UnitEnum | null $navigationGroup = 'Reports & Analytics';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'reports/vehicle-lto-due';

    protected string $view = 'filament.pages.reports.report-page';

    public string $report_type = 'lto_renewal';
    public string $date_preset = 'all';
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?string $branch_office_id = null;
    public ?string $collateral_type = null;
    public string $approval_status = 'all';

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
        $query = ChattelCollateral::query()
            ->whereNotNull('registration_renewal_date')
            ->whereHas('loanApplication', function ($lq) use ($allowed) {
                if (is_array($allowed)) {
                    $lq->whereIn('branch_office_id', $allowed);
                }
            });

        $count = $query->whereDate('registration_renewal_date', '<=', now()->addDays(30))->count();

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
        $this->approval_status = 'all';
        $this->resetTable();
        $this->generatePreview();
    }

    public function updatedApprovalStatus(): void
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
            'approval_status'  => $this->approval_status,
        ];

        $this->previewData = ReportExportService::getReportData($this->report_type, $filters, auth()->user());
    }

    public function table(Table $table): Table
    {
        [$startDate, $endDate] = $this->getDateRange();
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
                if ($this->approval_status === 'Approved') {
                    $lq->where('approval_status', 'Approved');
                } elseif ($this->approval_status === 'Disapproved_Pending') {
                    $lq->where(function ($q) {
                        $q->where('approval_status', '!=', 'Approved')
                          ->orWhereNull('approval_status');
                    });
                } elseif ($this->approval_status === 'Disapproved') {
                    $lq->where('approval_status', 'Disapproved');
                } elseif ($this->approval_status === 'Pending') {
                    $lq->where(function ($q) {
                        $q->whereIn('approval_status', ['Pending', 'Pending Approval'])
                          ->orWhereNull('approval_status');
                    });
                }
            });

        if ($this->date_preset === 'this_month' && $endDate) {
            $query->whereDate('registration_renewal_date', '<=', $endDate);
        } elseif ($startDate && $endDate) {
            $query->whereBetween('registration_renewal_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->whereDate('registration_renewal_date', '>=', $startDate);
        } elseif ($endDate) {
            $query->whereDate('registration_renewal_date', '<=', $endDate);
        }

        return $table
            ->query($query->orderBy('registration_renewal_date'))
            ->striped()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->persistColumnSearchesInSession()
            ->emptyStateHeading('No LTO Renewal Dues')
            ->emptyStateDescription('All tracked chattel vehicles have up-to-date LTO registrations.')
            ->emptyStateIcon('heroicon-o-truck')
            ->columns([
                TextColumn::make('loanApplication.created_at')
                    ->label('Date Filed')
                    ->date('M d, Y')
                    ->placeholder('—')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            \App\Models\LoanApplication::select('created_at')
                                ->whereColumn('loan_applications.id', 'chattel_collaterals.loan_application_id')
                                ->limit(1),
                            $direction
                        );
                    }),

                TextColumn::make('loanApplication.member.last_name')
                    ->label('Borrower Name')
                    ->formatStateUsing(fn ($state, $record) => $record?->loanApplication?->member?->natural_full_name ?? $record?->loanApplication?->member?->full_name ?? $state)
                    ->description(function (ChattelCollateral $record) {
                        $branch = $record->loanApplication?->branchOffice?->name ?? 'No Branch';
                        $contact = $record->loanApplication?->member?->phone_no ?: $record->loanApplication?->member?->mobile_no;
                        return $contact ? "{$branch} • 📞 {$contact}" : $branch;
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('loanApplication.member', function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%")
                              ->orWhere('phone_no', 'like', "%{$search}%")
                              ->orWhere('mobile_no', 'like', "%{$search}%");
                        })->orWhereHas('loanApplication.branchOffice', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
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
                    })
                    ->weight('semibold'),

                TextColumn::make('loanApplication.requested_amount')
                    ->label('Loan Amount')
                    ->money('PHP')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            \App\Models\LoanApplication::select('requested_amount')
                                ->whereColumn('loan_applications.id', 'chattel_collaterals.loan_application_id')
                                ->limit(1),
                            $direction
                        );
                    })
                    ->alignEnd()
                    ->weight('bold')
                    ->summarize(
                        \Filament\Tables\Columns\Summarizers\Summarizer::make()
                            ->money('PHP')
                            ->label('Total Volume')
                            ->using(fn ($query) => $query ? (float) (clone $query)->sum('requested_amount') : 0)
                    ),

                TextColumn::make('owner_name')
                    ->label('Owner Name')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('plate_no')
                    ->label('Plate #')
                    ->formatStateUsing(fn ($state, $record) => $state ?: ($record->mv_file_no ?: 'No Plate'))
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->color('primary')
                    ->copyable()
                    ->copyMessage('Plate / MV File number copied to clipboard'),

                TextColumn::make('vehicle_description')
                    ->label('Vehicle Info')
                    ->state(fn (ChattelCollateral $record) => ($record->year_model ? "{$record->year_model} " : '') . ($record->vehicle_description ?: 'Vehicle'))
                    ->description(fn (ChattelCollateral $record) => 'Eng: ' . ($record->engine_no ?: 'N/A') . ' | Ch: ' . ($record->chassis_no ?: 'N/A'))
                    ->wrap()
                    ->sortable(),

                TextColumn::make('loanApplication.approval_status')
                    ->label('Approval Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ?: 'Pending')
                    ->color(fn ($state) => match ($state) {
                        'Approved'         => 'success',
                        'Disapproved'      => 'danger',
                        'Pending Approval' => 'warning',
                        default            => 'gray',
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            \App\Models\LoanApplication::select('approval_status')
                                ->whereColumn('loan_applications.id', 'chattel_collaterals.loan_application_id')
                                ->limit(1),
                            $direction
                        );
                    }),

                TextColumn::make('registration_renewal_date')
                    ->date('M d, Y')
                    ->sortable()
                    ->label('LTO Renewal Date')
                    ->summarize(
                        Count::make()
                            ->label('Total Vehicles')
                    ),

                TextColumn::make('lto_urgency')
                    ->label('LTO Status')
                    ->badge()
                    ->state(function (ChattelCollateral $record) {
                        $date = $record->registration_renewal_date;
                        if (!$date) return 'NO DATE';
                        $c = Carbon::parse($date);
                        if ($c->isPast()) {
                            $days = abs((int) now()->diffInDays($c));
                            return $days === 0 ? 'EXPIRED (Today)' : "EXPIRED ({$days}d ago)";
                        }
                        $days = (int) now()->diffInDays($c);
                        if ($days === 0) return 'DUE TODAY';
                        if ($days <= 30) return "DUE SOON ({$days}d left)";
                        return "ACTIVE ({$days}d left)";
                    })
                    ->color(function (ChattelCollateral $record) {
                        $date = $record->registration_renewal_date;
                        if (!$date) return 'gray';
                        $c = Carbon::parse($date);
                        if ($c->isPast()) return 'danger';
                        return (int) now()->diffInDays($c) <= 30 ? 'warning' : 'success';
                    })
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('registration_renewal_date', $direction)),

                TextColumn::make('engine_no')
                    ->label('Engine No.')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('chassis_no')
                    ->label('Chassis No.')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('mv_file_no')
                    ->label('MV File No.')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('encumbered_to')
                    ->label('Encumbered To')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('view')
                        ->label('View Loan Application')
                        ->icon('heroicon-m-eye')
                        ->color('gray')
                        ->url(fn (ChattelCollateral $record) => $record->loanApplication ? LoanApplicationResource::getUrl('view', ['record' => $record->loanApplication]) : '#'),

                    Action::make('updateRenewal')
                        ->label('Update LTO Renewal')
                        ->icon('heroicon-m-pencil-square')
                        ->color('warning')
                        ->modalHeading(fn (ChattelCollateral $record) => "Update LTO Renewal — " . ($record->plate_no ?: $record->mv_file_no))
                        ->modalDescription(fn (ChattelCollateral $record) => "Borrower: " . ($record->loanApplication?->member?->full_name ?? '—') . " | Vehicle: {$record->vehicle_description}")
                        ->modalSubmitActionLabel('Save Renewal Details')
                        ->form([
                            TextInput::make('owner_name')
                                ->label('Registered Vehicle Owner')
                                ->placeholder('e.g. Juan Dela Cruz')
                                ->default(fn (ChattelCollateral $record) => $record->owner_name),
                            DatePicker::make('registration_renewal_date')
                                ->label('New Registration Renewal Due Date')
                                ->default(fn (ChattelCollateral $record) => $record->registration_renewal_date)
                                ->required(),
                            TextInput::make('lto_or_no')
                                ->label('Latest LTO Official Receipt (OR) #')
                                ->placeholder('e.g. 2026-1049281')
                                ->default(fn (ChattelCollateral $record) => $record->loanApplication?->lto_or_no),
                            Select::make('encumbered_to')
                                ->label('Encumbrance Status')
                                ->options([
                                    'OIC ENCUMBERED' => 'OIC Encumbered',
                                    'REL/E'          => 'Released / Discharged',
                                    'NEW'            => 'New / Unregistered',
                                ])
                                ->default(fn (ChattelCollateral $record) => $record->encumbered_to ?? 'OIC ENCUMBERED'),
                        ])
                        ->action(function (ChattelCollateral $record, array $data): void {
                            $updateData = [
                                'registration_renewal_date' => $data['registration_renewal_date'],
                                'encumbered_to'             => $data['encumbered_to'] ?? $record->encumbered_to,
                            ];
                            if (array_key_exists('owner_name', $data)) {
                                $updateData['owner_name'] = $data['owner_name'];
                            }
                            $record->update($updateData);

                            if ($record->loanApplication && isset($data['lto_or_no'])) {
                                $record->loanApplication->update([
                                    'lto_or_no' => $data['lto_or_no'],
                                ]);
                            }

                            $this->generatePreview();

                            Notification::make()
                                ->title('LTO Renewal Updated')
                                ->body("Vehicle " . ($record->plate_no ?: $record->mv_file_no) . " registration due date updated successfully.")
                                ->success()
                                ->send();
                        }),

                    Action::make('printNotice')
                        ->label('Print Renewal Notice')
                        ->icon('heroicon-m-printer')
                        ->color('success')
                        ->action(function (ChattelCollateral $record) {
                            $url = route('reports.vehicle-renewal-notice', $record) . '?autoprint=1';
                            $this->dispatch('print-report-silent', url: $url);
                        }),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('Actions'),
            ])
            ->defaultPaginationPageOption(10);
    }

    protected function getViewData(): array
    {
        return [
            'branches'                => $this->branches,
            'printUrl'                => $this->getPrintUrl(),
            'excelUrl'                => $this->getExcelUrl(),
            'hasCollateralFilter'     => false,
            'hasApprovalStatusFilter' => true,
            'approval_status'         => $this->approval_status,
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
            'approval_status'  => $this->approval_status,
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
            'approval_status'  => $this->approval_status,
        ]);

        return route('reports.export-excel') . '?' . $params;
    }
}
