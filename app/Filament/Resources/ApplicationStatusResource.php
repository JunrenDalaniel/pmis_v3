<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApplicationStatusResource\Pages;
use App\Models\ApplicationStatus;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class ApplicationStatusResource extends Resource
{
    protected static ?string $model = ApplicationStatus::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-tag';

    protected static string | UnitEnum | null $navigationGroup = 'System Administration';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Application Statuses';

    protected static ?string $modelLabel = 'Application Status';

    protected static ?string $pluralModelLabel = 'Application Statuses';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Application Status Details')
                    ->description('Manage application workflow status labels and badge colors')
                    ->icon('heroicon-o-tag')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Status Name')
                                ->placeholder('e.g. For Approval')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('code')
                                ->label('Status Code / Slug')
                                ->placeholder('e.g. for_approval')
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                        ]),
                        Grid::make(3)->schema([
                            Select::make('color')
                                ->label('Badge Color')
                                ->options([
                                    'gray' => '⚪ Gray (Neutral)',
                                    'info' => '🔵 Info (Blue)',
                                    'primary' => '🟣 Primary (Indigo)',
                                    'success' => '🟢 Success (Green)',
                                    'warning' => '🟡 Warning (Yellow)',
                                    'danger' => '🔴 Danger (Red)',
                                ])
                                ->default('info')
                                ->required(),
                            TextInput::make('sort_order')
                                ->label('Sort Order')
                                ->numeric()
                                ->default(0)
                                ->required(),
                            Toggle::make('is_active')
                                ->label('Active Status')
                                ->default(true)
                                ->required(),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sort')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Status Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Status Code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('color')
                    ->label('Badge Preview')
                    ->badge()
                    ->color(fn ($state) => $state ?? 'info'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('sort_order', 'asc')
            ->actions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApplicationStatuses::route('/'),
            'create' => Pages\CreateApplicationStatus::route('/create'),
            'edit' => Pages\EditApplicationStatus::route('/{record}/edit'),
        ];
    }
}
