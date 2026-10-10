<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountLogResource\Pages;
use App\Models\AccountLog;
use App\Models\BranchOffice;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AccountLogResource extends Resource
{
    protected static ?string $model = AccountLog::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-finger-print';

    protected static string | UnitEnum | null $navigationGroup = 'System Administration';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Account / Login Logs';

    protected static ?string $modelLabel = 'Account Log';

    protected static ?string $pluralModelLabel = 'Account Logs';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        $query = AccountLog::active();

        if ($user) {
            $allowedBranches = $user->getAllowedBranchIds();
            if ($allowedBranches !== null) {
                $query->where(function ($q) use ($allowedBranches, $user) {
                    $q->whereIn('branch_office_id', $allowedBranches)
                      ->orWhere('user_id', $user->id);
                });
            }
        }

        $count = $query->count();
        return $count > 0 ? "{$count} Active" : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Staff members currently logged in';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'     => 'success',
                        'logged_out' => 'gray',
                        'failed'     => 'danger',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active'     => '🟢 Active Now',
                        'logged_out' => '⚪ Logged Out',
                        'failed'     => '🔴 Failed Attempt',
                        default      => ucfirst($state),
                    })
                    ->sortable(),

                TextColumn::make('user_name')
                    ->label('Staff / User')
                    ->description(fn (AccountLog $record): ?string => $record->user_email)
                    ->searchable(['user_name', 'user_email'])
                    ->sortable(),

                TextColumn::make('role_name')
                    ->label('Role')
                    ->state(function (AccountLog $record): string {
                        $role = $record->role_name;
                        if (! $role) {
                            $role = $record->user?->role?->name ?? $record->user?->roles?->first()?->name;
                        }
                        if (! $role) {
                            return $record->user_id ? 'Staff' : 'Unregistered / Guest';
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
                        'Unregistered / Guest' => 'gray',
                        default => 'info',
                    })
                    ->searchable(['role_name'])
                    ->sortable(),

                TextColumn::make('branch_name')
                    ->label('Branch')
                    ->badge()
                    ->color('primary')
                    ->placeholder('Head Office / System')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('host')
                    ->label('Workstation / Host')
                    ->fontFamily(\Filament\Support\Enums\FontFamily::Mono)
                    ->color('gray')
                    ->placeholder('Localhost / Unresolved')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->fontFamily(\Filament\Support\Enums\FontFamily::Mono)
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('isp')
                    ->label('ISP / Network')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Localhost (Dev)')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('device')
                    ->label('Device & Browser')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('login_at')
                    ->label('Login Time')
                    ->dateTime('F d, Y h:i:s A')
                    ->description(fn (AccountLog $record): string => $record->login_at ? $record->login_at->diffForHumans() : '')
                    ->sortable(),

                TextColumn::make('logout_at')
                    ->label('Logout Time')
                    ->formatStateUsing(function ($state, AccountLog $record): string {
                        if ($state) {
                            return \Carbon\Carbon::parse($state)->format('F d, Y h:i:s A');
                        }
                        return $record->is_active ? '🟢 Currently Online' : '—';
                    })
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('duration')
                    ->label('Duration')
                    ->badge()
                    ->color(fn (AccountLog $record): string => match (true) {
                        $record->is_active         => 'success',
                        $record->status === 'failed' => 'gray',
                        default                    => 'info',
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('role_name')
                    ->label('Filter by Role')
                    ->options(function () {
                        $roles = \App\Models\Role::pluck('name', 'name')->toArray();
                        if (isset($roles['super_admin'])) {
                            $roles['super_admin'] = 'Super Admin';
                        }
                        return $roles;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        $selected = $data['value'];
                        return $query->where(function (Builder $q) use ($selected) {
                            $q->where('role_name', $selected)
                              ->orWhereHas('user', function (Builder $userQuery) use ($selected) {
                                  $userQuery->whereHas('role', fn ($rq) => $rq->where('name', $selected)->orWhere('code', $selected))
                                            ->orWhereHas('roles', fn ($rq) => $rq->where('name', $selected)->orWhere('code', $selected));
                              });
                        });
                    }),

                SelectFilter::make('status')
                    ->options([
                        'active'     => 'Active Sessions Now',
                        'logged_out' => 'Logged Out',
                        'failed'     => 'Failed Attempts',
                    ]),

                SelectFilter::make('branch_office_id')
                    ->label('Branch Office')
                    ->options(fn () => BranchOffice::orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable(),

                Filter::make('login_at')
                    ->form([
                        DatePicker::make('login_from')->label('Login From Date'),
                        DatePicker::make('login_until')->label('Login Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['login_from'] ?? null, fn (Builder $q, $date) => $q->whereDate('login_at', '>=', $date))
                            ->when($data['login_until'] ?? null, fn (Builder $q, $date) => $q->whereDate('login_at', '<=', $date));
                    }),
            ])
            ->actions([
                Action::make('force_logout')
                    ->label('Force Logout')
                    ->icon('heroicon-o-arrow-left-on-rectangle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Terminate User Session')
                    ->modalDescription(fn (AccountLog $record): string => "Sigurado ka bang nais mong i-terminate ang active session ni {$record->user_name} sa host [{$record->host}]? Puputulin agad ang kanyang koneksyon sa system.")
                    ->visible(function (AccountLog $record): bool {
                        if (! $record->is_active) {
                            return false;
                        }
                        $currentUser = auth()->user();
                        if (! $currentUser || ! ($currentUser->hasRole(['super_admin', 'Super Admin']) || $currentUser->hasPermissionTo('Update:AccountLog'))) {
                            return false;
                        }
                        if ($currentUser->id === $record->user_id) {
                            return false;
                        }
                        if ($record->role_name === 'Super Admin' && ! $currentUser->hasRole(['super_admin', 'Super Admin'])) {
                            return false;
                        }
                        return true;
                    })
                    ->action(function (AccountLog $record) {
                        // 1. Mark account log as logged_out
                        $record->update([
                            'logout_at' => now(),
                            'status' => 'logged_out',
                            'failure_reason' => 'Terminated by Administrator (Force Logout)',
                        ]);

                        // 2. Terminate session from database sessions table if present
                        if ($record->session_id) {
                            \Illuminate\Support\Facades\DB::table('sessions')
                                ->where('id', $record->session_id)
                                ->delete();
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Session Terminated')
                            ->body("Matagumpay na na-force logout ang active session ni {$record->user_name}.")
                            ->warning()
                            ->send();
                    }),

                Action::make('view_details')
                    ->label('Details')
                    ->icon('heroicon-o-information-circle')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->modalHeading(fn (AccountLog $record): string => "Authentication Audit — #{$record->id}")
                    ->modalDescription(fn (AccountLog $record): string => "{$record->user_name} • {$record->branch_name}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (AccountLog $record) => view('filament.modals.account-log-detail-modal', [
                        'log' => $record,
                    ])),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['user.role', 'user.roles', 'branchOffice']);
        $user = auth()->user();

        if ($user) {
            $allowedBranches = $user->getAllowedBranchIds();
            if ($allowedBranches !== null) {
                $query->where(function (Builder $q) use ($allowedBranches, $user) {
                    $q->whereIn('branch_office_id', $allowedBranches)
                      ->orWhere('user_id', $user->id);
                });
            }
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccountLogs::route('/'),
        ];
    }
}
