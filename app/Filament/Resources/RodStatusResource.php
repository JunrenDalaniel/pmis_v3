<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RodStatusResource\Pages;
use App\Models\RodStatus;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class RodStatusResource extends Resource
{
    protected static ?string $model = RodStatus::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-list-bullet';

    protected static string | UnitEnum | null $navigationGroup = 'System Administration';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'ROD Statuses';

    protected static ?string $modelLabel = 'ROD Status';

    protected static ?string $pluralModelLabel = 'ROD Statuses';

    // protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Process Step Information')
                    ->description('Workflow step sequence number and status details')
                    ->icon('heroicon-o-list-bullet')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('step_number')
                                ->label('Step Number')
                                ->numeric()
                                ->required()
                                ->minValue(1),
                            TextInput::make('name')
                                ->label('Status Name')
                                ->placeholder('e.g. Examination')
                                ->required()
                                ->maxLength(255),
                            Toggle::make('is_active')
                                ->label('Active Status')
                                ->default(true)
                                ->required(),
                        ]),
                        Textarea::make('description')
                            ->label('Process Description')
                            ->placeholder('Describe what takes place during this external process step...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('step_number', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('step_number')
                    ->label('Step #')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('ROD Status Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->wrap(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRodStatuses::route('/'),
            'create' => Pages\CreateRodStatus::route('/create'),
            'edit' => Pages\EditRodStatus::route('/{record}/edit'),
        ];
    }
}
