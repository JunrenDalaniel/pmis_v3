<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';

    protected static string | UnitEnum | null $navigationGroup = 'System Administration';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Activity Logs';

    protected static ?string $modelLabel = 'Activity Log';

    protected static ?string $pluralModelLabel = 'Activity Logs';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $userId = auth()->id() ?? 'guest';

        return cache()->remember("nav_badge_activity_logs_{$userId}", 30, function () {
            $user = auth()->user();
            $query = Activity::whereDate('created_at', today());

            if ($user) {
                $allowedBranches = $user->getAllowedBranchIds();
                if ($allowedBranches !== null) {
                    $branchNames = \App\Models\BranchOffice::whereIn('id', $allowedBranches)->pluck('name')->toArray();
                    $query->where(function (Builder $q) use ($allowedBranches, $branchNames, $user) {
                        $q->whereIn('properties->branch_office_id', $allowedBranches)
                          ->orWhereIn('properties->branch', $branchNames)
                          ->orWhereHasMorph('causer', [\App\Models\User::class], fn ($uq) => $uq->whereIn('branch_office_id', $allowedBranches)->orWhere('id', $user->id))
                          ->orWhereHasMorph('subject', [\App\Models\LoanApplication::class], fn ($lq) => $lq->whereIn('branch_office_id', $allowedBranches));
                    });
                }
            }

            $count = $query->count();
            return $count > 0 ? (string) $count : null;
        });
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Total activity logs recorded today';
    }

    public static function getSafeSubject(Activity $record): ?\Illuminate\Database\Eloquent\Model
    {
        if (empty($record->subject_type) || ! class_exists($record->subject_type)) {
            return null;
        }

        try {
            return $record->subject;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('F d, Y h:i:s A')
                    ->description(fn (Activity $record): string => $record->created_at ? $record->created_at->diffForHumans() : '')
                    ->sortable(),

                TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->icon(fn (Activity $record): string => match (true) {
                        str_contains((string) $record->description, 'APPROVED')    => 'heroicon-m-check-circle',
                        str_contains((string) $record->description, 'DISAPPROVED') => 'heroicon-m-x-circle',
                        str_contains((string) $record->description, 'upload')      => 'heroicon-m-paper-clip',
                        $record->event === 'created'                               => 'heroicon-m-plus-circle',
                        $record->event === 'deleted'                               => 'heroicon-m-trash',
                        default                                                    => 'heroicon-m-pencil-square',
                    })
                    ->color(fn (Activity $record): string => match (true) {
                        str_contains((string) $record->description, 'APPROVED')    => 'success',
                        str_contains((string) $record->description, 'DISAPPROVED') => 'danger',
                        str_contains((string) $record->description, 'upload')      => 'warning',
                        $record->event === 'created'                               => 'success',
                        $record->event === 'deleted'                               => 'danger',
                        default                                                    => 'info',
                    })
                    ->formatStateUsing(fn (?string $state, Activity $record): string => match (true) {
                        str_contains((string) $record->description, 'APPROVED')    => 'Approved',
                        str_contains((string) $record->description, 'DISAPPROVED') => 'Disapproved',
                        str_contains((string) $record->description, 'upload')      => 'File Upload',
                        $state === 'created'                                       => 'Created',
                        $state === 'deleted'                                       => 'Deleted',
                        default                                                    => 'Updated',
                    })
                    ->sortable(),

                TextColumn::make('subject_id')
                    ->label('Target Record')
                    ->formatStateUsing(function (Activity $record): string {
                        $subject = static::getSafeSubject($record);
                        if ($subject instanceof \App\Models\LoanApplication) {
                            return $subject->application_no ?: "Loan #{$record->subject_id}";
                        }
                        if ($subject instanceof \App\Models\Member) {
                            return $subject->full_name ?: "Member #{$record->subject_id}";
                        }
                        $shortType = class_basename($record->subject_type ?? '');
                        return $shortType ? "{$shortType} #{$record->subject_id}" : (string) $record->subject_id;
                    })
                    ->description(function (Activity $record): ?string {
                        $subject = static::getSafeSubject($record);
                        if ($subject instanceof \App\Models\LoanApplication) {
                            $borrower = $subject->member?->full_name;
                            return $borrower ? "Borrower: {$borrower}" : null;
                        }
                        if ($subject instanceof \App\Models\Member) {
                            return $subject->member_no ? "No: {$subject->member_no}" : null;
                        }
                        return null;
                    })
                    ->searchable(),

                TextColumn::make('modified_fields')
                    ->label('Modified Fields')
                    ->state(function (Activity $record): array {
                        $props = $record->properties ? $record->properties->toArray() : [];
                        $attributes = array_keys($props['attributes'] ?? []);
                        $attributes = array_values(array_filter($attributes, fn ($k) => $k !== 'updated_at'));
                        if (empty($attributes)) {
                            return [];
                        }
                        $labels = [
                            'approval_status'   => 'Approval Status',
                            'sub_status'        => 'Deficiency',
                            'requested_amount'  => 'Amount',
                            'branch_office_id'  => 'Branch',
                            'loan_account_no'   => 'Account No',
                            'date_granted'      => 'Date Granted',
                            'maturity_date'     => 'Maturity Date',
                            'status_changed_at' => 'Status Date',
                            'notes'             => 'Notes',
                            'is_active'         => 'Active Status',
                        ];
                        return array_map(fn ($k) => $labels[$k] ?? ucwords(str_replace('_', ' ', $k)), array_slice($attributes, 0, 3));
                    })
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('causer.name')
                    ->label('User / Performed By')
                    ->placeholder('System')
                    ->wrap()
                    ->description(function (Activity $record): ?string {
                        $device = $record->properties['client_device'] ?? null;
                        return $device ?? $record->causer?->email ?? null;
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('role')
                    ->label('Role')
                    ->state(function (Activity $record): string {
                        $role = $record->properties['role'] ?? null;
                        if (! $role) {
                            $role = $record->causer?->role?->name ?? $record->causer?->roles?->first()?->name;
                        }
                        if (! $role) {
                            return $record->causer ? 'Staff' : 'System';
                        }
                        return match ($role) {
                            'super_admin' => 'Super Admin',
                            default => $role,
                        };
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Super Admin' => 'danger',
                        'Branch Manager', 'Operation Division Manager (ODM)', 'Chief Executive Officer (CEO)' => 'warning',
                        'Audit', 'Compliance Specialist' => 'info',
                        'System' => 'gray',
                        default => 'info',
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('properties->role', 'like', "%{$search}%")
                            ->orWhereHasMorph('causer', [\App\Models\User::class], function (Builder $userQuery) use ($search) {
                                $userQuery->whereHas('role', fn ($rq) => $rq->where('name', 'like', "%{$search}%"))
                                          ->orWhereHas('roles', fn ($rq) => $rq->where('name', 'like', "%{$search}%"));
                            });
                    }),

                TextColumn::make('properties.branch')
                    ->label('Branch')
                    ->badge()
                    ->color('primary')
                    ->placeholder('Head Office / System')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('properties->branch', 'like', "%{$search}%");
                    }),

                TextColumn::make('properties.ip')
                    ->label('IP Address')
                    ->fontFamily(\Filament\Support\Enums\FontFamily::Mono)
                    ->color('gray')
                    ->placeholder('—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('properties->ip', 'like', "%{$search}%");
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('description')
                    ->label('Activity Description')
                    ->searchable()
                    ->limit(55)
                    ->tooltip(fn (Activity $record): string => (string) $record->description)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('role')
                    ->label('Staff Role')
                    ->options(function () {
                        $roles = \App\Models\Role::pluck('name', 'name')->toArray();
                        if (isset($roles['super_admin'])) {
                            $roles['super_admin'] = 'Super Admin';
                        }
                        $roles['System'] = 'System Action';
                        return $roles;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        $selected = $data['value'];
                        if ($selected === 'System') {
                            return $query->whereNull('causer_id');
                        }
                        return $query->where(function (Builder $q) use ($selected) {
                            $q->where('properties->role', $selected)
                              ->orWhereHasMorph('causer', [\App\Models\User::class], function (Builder $userQuery) use ($selected) {
                                  $userQuery->whereHas('role', fn ($rq) => $rq->where('name', $selected)->orWhere('code', $selected))
                                            ->orWhereHas('roles', fn ($rq) => $rq->where('name', $selected)->orWhere('code', $selected));
                              });
                        });
                    }),

                SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),

                SelectFilter::make('log_name')
                    ->label('Module')
                    ->options([
                        'loan_application' => 'Loan Application',
                        'member'           => 'Member Record',
                        'collateral'       => 'Collateral',
                    ]),

                SelectFilter::make('branch')
                    ->label('Branch Office')
                    ->options(fn () => \App\Models\BranchOffice::orderBy('name')->pluck('name', 'name')->toArray())
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['value'] ?? null, fn (Builder $q, $branch) => $q->where('properties->branch', $branch));
                    }),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('logged_from')->label('From Date'),
                        DatePicker::make('logged_until')->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['logged_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['logged_until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Action::make('view_diff')
                    ->label('View Diff')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->modalHeading(fn (Activity $record): string => "Audit Log Details — #{$record->id}")
                    ->modalDescription(fn (Activity $record): string => $record->description ?? 'Detailed field change comparison')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (Activity $record) => view('filament.modals.activity-log-diff-modal', [
                        'activity' => $record,
                    ])),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['causer.role', 'causer.roles']);
        $user = auth()->user();

        if ($user) {
            $allowedBranches = $user->getAllowedBranchIds();
            if ($allowedBranches !== null) {
                $branchNames = \App\Models\BranchOffice::whereIn('id', $allowedBranches)->pluck('name')->toArray();

                $query->where(function (Builder $q) use ($allowedBranches, $branchNames, $user) {
                    $q->whereIn('properties->branch_office_id', $allowedBranches)
                      ->orWhereIn('properties->branch', $branchNames)
                      ->orWhereHasMorph('causer', [\App\Models\User::class], function (Builder $userQuery) use ($allowedBranches, $user) {
                          $userQuery->whereIn('branch_office_id', $allowedBranches)
                                    ->orWhere('id', $user->id);
                      })
                      ->orWhereHasMorph('subject', [\App\Models\LoanApplication::class], function (Builder $loanQuery) use ($allowedBranches) {
                          $loanQuery->whereIn('branch_office_id', $allowedBranches);
                      })
                      ->orWhereHasMorph('subject', [\App\Models\Member::class], function (Builder $memberQuery) use ($allowedBranches) {
                          $memberQuery->whereIn('branch_office_id', $allowedBranches);
                      });
                });
            }
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
        ];
    }
}
