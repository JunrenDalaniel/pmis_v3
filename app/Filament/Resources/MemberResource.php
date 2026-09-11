<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MemberResource\Pages;
use App\Filament\Resources\MemberResource\RelationManagers;
use App\Models\Member;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-identification';

    protected static string | UnitEnum | null $navigationGroup = 'Membership Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Members';

    protected static ?string $modelLabel = 'Member';

    protected static ?string $pluralModelLabel = 'Members';

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['member_no', 'first_name', 'last_name', 'suffix', 'email', 'phone'];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()->with(['branchOffice']);

        $user = auth()->user();
        if ($user) {
            $allowedBranches = $user->getAllowedBranchIds();
            if (is_array($allowedBranches)) {
                $query->whereIn('branch_office_id', $allowedBranches);
            }
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal Information')
                    ->description('Primary member identification and legal details')
                    ->icon('heroicon-o-user')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('member_no')
                                ->label('Member ID')
                                ->placeholder('e.g. API-2026-00008')
                                ->prefixIcon('heroicon-o-identification')
                                ->default(function () {
                                    $year = date('Y');
                                    $nextId = (\App\Models\Member::max('id') ?? 0) + 1;
                                    return 'API-' . $year . '-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
                                })
                                ->disabled()
                                ->dehydrated()
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                            TextInput::make('first_name')
                                ->label('First Name')
                                ->placeholder('e.g. Juan')
                                ->prefixIcon('heroicon-o-user')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('middle_name')
                                ->label('Middle Name')
                                ->placeholder('e.g. Santos')
                                ->prefixIcon('heroicon-o-user')
                                ->maxLength(255),
                            TextInput::make('last_name')
                                ->label('Last Name')
                                ->placeholder('e.g. Dela Cruz')
                                ->prefixIcon('heroicon-o-user')
                                ->required()
                                ->maxLength(255),
                        ]),
                        Grid::make(4)->schema([
                            TextInput::make('suffix')
                                ->label('Name Suffix')
                                ->placeholder('e.g. Jr., Sr., III, CPA, Ph.D.')
                                ->prefixIcon('heroicon-o-user-circle')
                                ->maxLength(255),
                            DatePicker::make('birth_date')
                                ->label('Date of Birth')
                                ->prefixIcon('heroicon-o-calendar'),
                            Select::make('sex')
                                ->label('Sex')
                                ->prefixIcon('heroicon-o-users')
                                ->options([
                                    'Male' => 'Male',
                                    'Female' => 'Female',
                                ]),
                            Select::make('civil_status')
                                ->label('Civil Status')
                                ->prefixIcon('heroicon-o-heart')
                                ->options([
                                    'Single' => 'Single',
                                    'Married' => 'Married',
                                    'Widowed' => 'Widowed',
                                    'Separated' => 'Separated',
                                    'Divorced' => 'Divorced',
                                ]),
                        ]),
                        Grid::make(4)->schema([
                            TextInput::make('tin')
                                ->label('TIN (Taxpayer Identification Number)')
                                ->placeholder('e.g. 123-456-789-000')
                                ->prefixIcon('heroicon-o-document-text')
                                ->mask('999-999-999-999')
                                ->maxLength(255),
                            TextInput::make('email')
                                ->label('Email Address')
                                ->placeholder('e.g. member@example.com')
                                ->prefixIcon('heroicon-o-envelope')
                                ->email()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                            TextInput::make('phone')
                                ->label('Phone Number')
                                ->placeholder('e.g. 09171234567')
                                ->prefixIcon('heroicon-o-phone')
                                ->tel()
                                ->maxLength(255),
                            Select::make('branch_office_id')
                                ->label('Assigned OIC Branch')
                                ->prefixIcon('heroicon-o-building-office-2')
                                ->options(function () {
                                    $user = auth()->user();
                                    $allowed = $user?->getAllowedBranchIds();
                                    $query = \App\Models\BranchOffice::query();
                                    if (is_array($allowed)) {
                                        $query->whereIn('id', $allowed);
                                    }
                                    return $query->pluck('name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->default(function () {
                                    $user = auth()->user();
                                    $allowed = $user?->getAllowedBranchIds();
                                    return (is_array($allowed) && count($allowed) > 0) ? $allowed[0] : $user?->branch_office_id;
                                })
                                ->disabled(function () {
                                    $user = auth()->user();
                                    $allowed = $user?->getAllowedBranchIds();
                                    return is_array($allowed) && count($allowed) === 1;
                                })
                                ->dehydrated()
                                ->required(),
                        ]),
                        Grid::make(4)->schema([
                            Textarea::make('address')
                                ->label('Complete Address')
                                ->placeholder('e.g. Street, Barangay, City, Province')
                                ->rows(2)
                                ->columnSpan(3),
                            Toggle::make('is_active')
                                ->label('Active Member Status')
                                ->default(true)
                                ->required()
                                ->columnSpan(1),
                        ]),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal Information')
                    ->description('Primary member identification, government tax ID, and contact details')
                    ->icon('heroicon-o-identification')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('member_no')
                                ->label('Member ID')
                                ->badge()
                                ->color('primary')
                                ->icon('heroicon-o-hashtag')
                                ->copyable(),

                            TextEntry::make('full_name')
                                ->label('Full Name')
                                ->weight('bold')
                                ->icon('heroicon-o-user'),

                            TextEntry::make('birth_date')
                                ->label('Date of Birth')
                                ->date('F d, Y')
                                ->icon('heroicon-o-calendar'),

                            TextEntry::make('sex')
                                ->label('Sex / Gender')
                                ->badge()
                                ->color(fn ($state) => $state === 'Female' ? 'pink' : 'blue'),
                        ]),
                        Grid::make(4)->schema([
                            TextEntry::make('civil_status')
                                ->label('Civil Status')
                                ->badge()
                                ->color('gray'),

                            TextEntry::make('tin')
                                ->label('TIN (Taxpayer ID)')
                                ->placeholder('—')
                                ->fontFamily('mono')
                                ->icon('heroicon-o-document-text')
                                ->copyable(),

                            TextEntry::make('email')
                                ->label('Email Address')
                                ->icon('heroicon-o-envelope')
                                ->placeholder('—')
                                ->copyable(),

                            TextEntry::make('phone')
                                ->label('Phone Number')
                                ->icon('heroicon-o-phone')
                                ->placeholder('—')
                                ->copyable(),
                        ]),
                        Grid::make(4)->schema([
                            TextEntry::make('branchOffice.name')
                                ->label('Assigned OIC Branch')
                                ->badge()
                                ->color('success')
                                ->icon('heroicon-o-building-office-2'),

                            TextEntry::make('address')
                                ->label('Complete Address')
                                ->icon('heroicon-o-map-pin')
                                ->columnSpan(2),

                            IconEntry::make('is_active')
                                ->label('Active Member Status')
                                ->boolean(),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member_no')
                    ->label('Member ID')
                    ->description(fn ($record) => $record->branchOffice?->name)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Full Name')
                    ->description(fn ($record) => $record->email)
                    ->searchable(['last_name', 'first_name', 'middle_name', 'suffix', 'email'])
                    ->sortable('last_name'),
                Tables\Columns\TextColumn::make('birth_date')
                    ->label('Date of Birth')
                    ->date('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sex')
                    ->label('Sex')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('civil_status')
                    ->label('Civil Status')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('tin')
                    ->label('TIN')
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active Status')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date Registered')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_office_id')
                    ->label('Branch Office')
                    ->relationship('branchOffice', 'name', function ($query) {
                        $user = auth()->user();
                        if ($user) {
                            $allowedBranches = $user->getAllowedBranchIds();
                            if (is_array($allowedBranches)) {
                                $query->whereIn('id', $allowedBranches);
                            }
                        }
                        return $query;
                    }),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Action::make('print')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->alpineClickHandler(function ($record) {
                        $printUrl = route('members.print', ['record' => $record]);
                        return "(() => {
                            let f = document.getElementById('pmis_print_frame');
                            if (!f) {
                                f = document.createElement('iframe');
                                f.id = 'pmis_print_frame';
                                f.style.position = 'fixed';
                                f.style.right = '0';
                                f.style.bottom = '0';
                                f.style.width = '0';
                                f.style.height = '0';
                                f.style.border = '0';
                                document.body.appendChild(f);
                            }
                            f.onload = () => {
                                f.onload = null;
                                try {
                                    f.contentWindow.focus();
                                    f.contentWindow.print();
                                } catch (e) {
                                    console.error('Print error:', e);
                                }
                            };
                            f.src = '{$printUrl}' + (('{$printUrl}'.indexOf('?') !== -1 ? '&' : '?') + '_t=' + Date.now());
                        })()";
                    }),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
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
            RelationManagers\LoanApplicationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'view' => Pages\ViewMember::route('/{record}'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
        ];
    }
}
