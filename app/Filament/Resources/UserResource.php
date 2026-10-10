<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | UnitEnum | null $navigationGroup = 'System Administration';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['role', 'branchOffice', 'branches']);
        $user = auth()->user();

        if ($user) {
            $allowedBranches = $user->getAllowedBranchIds();
            if ($allowedBranches !== null) {
                $query->where(function (Builder $q) use ($allowedBranches, $user) {
                    $q->whereIn('branch_office_id', $allowedBranches)
                      ->orWhereHas('branches', fn (Builder $bq) => $bq->whereIn('branch_offices.id', $allowedBranches))
                      ->orWhere('id', $user->id);
                });
            }
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Account Identity')
                    ->description('System user personal identity and login credentials')
                    ->icon('heroicon-o-key')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Full Name')
                                ->placeholder('e.g. John Doe')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('email')
                                ->label('Email Address')
                                ->placeholder('e.g. johndoe@example.com')
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make('password')
                                ->label('Password')
                                ->placeholder('Enter a secure password')
                                ->password()
                                ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                ->dehydrated(fn ($state) => filled($state))
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->minLength(8)
                                ->maxLength(255),
                            TextInput::make('password_confirmation')
                                ->label('Confirm Password')
                                ->placeholder('Re-enter password')
                                ->password()
                                ->dehydrated(false)
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->same('password')
                                ->visible(fn (string $operation): bool => $operation === 'create'),
                        ]),
                    ]),

                Section::make('Role & Branch Assignment')
                    ->description('Assign organizational role, primary branch, and multi-branch security shield assignments')
                    ->icon('heroicon-o-shield-check')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('role_id')
                                ->label('Organizational Role')
                                ->relationship('role', 'name', function ($query) {
                                    $currentUser = auth()->user();
                                    if (! $currentUser?->hasRole(['super_admin', 'Super Admin'])) {
                                        $query->whereNotIn('name', ['super_admin', 'Super Admin'])
                                              ->where('code', '!=', 'super_admin');
                                    }
                                    return $query;
                                })
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('branch_office_id')
                                ->label('Primary OIC Branch')
                                ->relationship('branchOffice', 'name')
                                ->searchable()
                                ->preload()
                                ->helperText('Main primary branch office for single-branch users.'),
                        ]),
                        Select::make('branches')
                            ->label('🛡️ Multi-Branch Security Shield (For Area Managers & Floating Officers)')
                            ->relationship('branches', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Select multiple branches if this user manages or oversees an area with multiple branch offices.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role.name')
                    ->label('Role')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('branchOffice.name')
                    ->label('Primary Branch')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('branches.name')
                    ->label('Multi-Branch Shield')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role_id')
                    ->label('Organizational Role')
                    ->relationship('role', 'name'),
                Tables\Filters\SelectFilter::make('branch_office_id')
                    ->label('OIC Branch Office')
                    ->relationship('branchOffice', 'name'),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('reset_password')
                        ->label('Reset Password')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->visible(function (User $record): bool {
                            $currentUser = auth()->user();
                            if (! $currentUser || ! $currentUser->can('Update:User')) {
                                return false;
                            }
                            if ($record->hasRole(['super_admin', 'Super Admin']) && ! $currentUser->hasRole(['super_admin', 'Super Admin'])) {
                                return false;
                            }
                            return true;
                        })
                        ->schema([
                            TextInput::make('new_password')
                                ->label('New Password')
                                ->password()
                                ->required()
                                ->minLength(8),
                            TextInput::make('new_password_confirmation')
                                ->label('Confirm New Password')
                                ->password()
                                ->required()
                                ->same('new_password'),
                        ])
                        ->action(function (User $record, array $data) {
                            $record->update([
                                'password' => Hash::make($data['new_password']),
                            ]);

                            Notification::make()
                                ->title('Password Reset Successfully')
                                ->body("Password for {$record->name} has been updated.")
                                ->success()
                                ->send();
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
