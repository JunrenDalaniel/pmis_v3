<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\InteractsWithDashboardFilters;
use App\Filament\Resources\LoanApplicationResource;
use App\Models\ChattelCollateral;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class VehicleRenewalAlertWidget extends BaseWidget
{
    use InteractsWithDashboardFilters;

    protected static bool $isLazy = false;

    protected static ?string $heading = '🚨 Vehicle LTO Registration & Renewal Alerts';

    protected static ?int $sort = 8;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $allowed = $user?->getAllowedBranchIds();

        $query = ChattelCollateral::query()
            ->with(['loanApplication.member', 'loanApplication.branchOffice'])
            ->orderByRaw('CASE WHEN registration_renewal_date < NOW() THEN 0 ELSE 1 END')
            ->orderBy('registration_renewal_date', 'asc');

        if (is_array($allowed)) {
            $query->whereHas('loanApplication', fn ($q) => $q->whereIn('branch_office_id', $allowed));
        }

        [$startDate, $endDate] = $this->getFilterDateRange();
        $selectedBranch = $this->getFilterBranchId();
        $selectedCollateral = $this->getFilterCollateralType();
        $presetLabel = $this->getFilterPresetLabel();

        if ($selectedBranch) {
            $query->whereHas('loanApplication', fn ($q) => $q->where('branch_office_id', $selectedBranch));
        }

        if ($selectedCollateral && $selectedCollateral !== 'chattel_mortgage') {
            $query->whereRaw('1 = 0');
        }

        if ($startDate && $endDate) {
            $query->whereBetween('registration_renewal_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->whereDate('registration_renewal_date', '>=', $startDate);
        } elseif ($endDate) {
            $query->whereDate('registration_renewal_date', '<=', $endDate);
        }

        return $table
            ->query($query)
            ->heading('🚨 Vehicle LTO Registration & Renewal Alerts')
            ->description(function () use ($query, $presetLabel) {
                $checkQuery = (clone $query)
                    ->whereNotNull('registration_renewal_date')
                    ->where('registration_renewal_date', '<', now());

                $expiredCount = $checkQuery->count();
                if ($expiredCount > 0) {
                    return "⚠️ [{$presetLabel}] May {$expiredCount} na sasakyan na EXPIRED na ang LTO registration sa napiling saklaw.";
                }
                return "[{$presetLabel}] Lahat ng sasakyan ay updated o walang expired sa napiling saklaw.";
            })
            ->columns([
                Tables\Columns\TextColumn::make('plate_no')
                    ->label('Plate / MV File No.')
                    ->weight('bold')
                    ->state(fn (ChattelCollateral $record): string => $record->plate_no ?: ($record->mv_file_no ?: 'No Plate/MV'))
                    ->description(fn (ChattelCollateral $record): ?string => $record->mv_file_no && $record->plate_no ? "MV: {$record->mv_file_no}" : null)
                    ->searchable(['plate_no', 'mv_file_no']),

                Tables\Columns\TextColumn::make('vehicle_description')
                    ->label('Vehicle / Model')
                    ->state(function (ChattelCollateral $record): string {
                        $desc = $record->vehicle_description ?: 'Vehicle';
                        return $record->year_model ? "{$record->year_model} • {$desc}" : $desc;
                    })
                    ->description(fn (ChattelCollateral $record): ?string => $record->engine_no ? "Engine: {$record->engine_no}" : null)
                    ->searchable(['vehicle_description', 'engine_no', 'chassis_no']),

                Tables\Columns\TextColumn::make('borrower_name')
                    ->label('Borrower & Branch')
                    ->state(function (ChattelCollateral $record): string {
                        $member = $record->loanApplication?->member;
                        if (! $member) {
                            return 'No Member Assigned';
                        }
                        return "{$member->last_name}, {$member->first_name}";
                    })
                    ->description(fn (ChattelCollateral $record): string => $record->loanApplication?->branchOffice?->name ?? 'No Branch')
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('loanApplication.member', function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    }),

                Tables\Columns\TextColumn::make('registration_renewal_date')
                    ->label('Renewal Date')
                    ->date('M d, Y')
                    ->sortable()
                    ->placeholder('Walang Petsa'),

                Tables\Columns\TextColumn::make('renewal_status')
                    ->label('LTO Status')
                    ->badge()
                    ->state(function (ChattelCollateral $record): string {
                        $date = $record->registration_renewal_date;
                        if (! $date) {
                            return 'Walang Petsa';
                        }
                        $carbon = Carbon::parse($date);
                        if ($carbon->isPast()) {
                            $days = abs((int) now()->diffInDays($carbon));
                            return $days === 0 ? 'Expires Today!' : "Expired {$days}d ago";
                        }
                        $daysLeft = (int) now()->diffInDays($carbon);
                        if ($daysLeft <= 30) {
                            return "Due in {$daysLeft} days";
                        }
                        return 'Updated / Active';
                    })
                    ->color(function (ChattelCollateral $record): string {
                        $date = $record->registration_renewal_date;
                        if (! $date) {
                            return 'gray';
                        }
                        $carbon = Carbon::parse($date);
                        if ($carbon->isPast()) {
                            return 'danger';
                        }
                        $daysLeft = (int) now()->diffInDays($carbon);
                        if ($daysLeft <= 30) {
                            return 'warning';
                        }
                        return 'success';
                    })
                    ->icon(function (ChattelCollateral $record): string {
                        $date = $record->registration_renewal_date;
                        if (! $date) {
                            return 'heroicon-m-question-mark-circle';
                        }
                        $carbon = Carbon::parse($date);
                        if ($carbon->isPast()) {
                            return 'heroicon-m-exclamation-triangle';
                        }
                        $daysLeft = (int) now()->diffInDays($carbon);
                        if ($daysLeft <= 30) {
                            return 'heroicon-m-clock';
                        }
                        return 'heroicon-m-check-circle';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status_filter')
                    ->label('Filter LTO Status')
                    ->options([
                        'expired' => '🔴 Expired Lamang',
                        'due_30'  => '🟡 Due in 30 Days',
                        'due_60'  => '🟡 Due in 60 Days',
                        'valid'   => '🟢 Valid / Active',
                    ])
                    ->query(function (Builder $query, array $data) {
                        $val = $data['value'] ?? null;
                        if ($val === 'expired') {
                            $query->whereNotNull('registration_renewal_date')
                                  ->where('registration_renewal_date', '<', now());
                        } elseif ($val === 'due_30') {
                            $query->whereNotNull('registration_renewal_date')
                                  ->whereBetween('registration_renewal_date', [now(), now()->addDays(30)]);
                        } elseif ($val === 'due_60') {
                            $query->whereNotNull('registration_renewal_date')
                                  ->whereBetween('registration_renewal_date', [now(), now()->addDays(60)]);
                        } elseif ($val === 'valid') {
                            $query->whereNotNull('registration_renewal_date')
                                  ->where('registration_renewal_date', '>=', now()->addDays(30));
                        }
                    }),
            ])
            ->actions([
                Action::make('view_loan')
                    ->label('View Loan')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(function (ChattelCollateral $record): ?string {
                        if (! $record->loanApplication) {
                            return null;
                        }
                        $app = $record->loanApplication;
                        if ($app->approval_status === 'Pending Approval') {
                            return \App\Filament\Resources\ForApprovalResource::getUrl('edit', ['record' => $app]);
                        }
                        if ($app->approval_status === 'Disapproved') {
                            return \App\Filament\Resources\DisapprovedLoanResource::getUrl('edit', ['record' => $app]);
                        }
                        return LoanApplicationResource::getUrl('edit', ['record' => $app]);
                    }),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}