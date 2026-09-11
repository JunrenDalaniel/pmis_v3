<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchOfficeResource\Pages;
use App\Models\BranchOffice;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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

class BranchOfficeResource extends Resource
{
    protected static ?string $model = BranchOffice::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string | UnitEnum | null $navigationGroup = 'System Administration';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'OIC Branch Offices';

    protected static ?string $modelLabel = 'Branch Office';

    protected static ?string $pluralModelLabel = 'Branch Offices';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Branch Office Details')
                    ->description('Location, assigned regional area, and operational status')
                    ->icon('heroicon-o-building-office')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('name')
                                ->label('Branch Name')
                                ->placeholder('e.g. BUTUAN')
                                ->required()
                                ->maxLength(255),
                            Select::make('area')
                                ->label('Regional Area Assignment')
                                ->options([
                                    'Area 1' => 'Area 1',
                                    'Area 2' => 'Area 2',
                                    'Area 3' => 'Area 3',
                                ])
                                ->required(),
                            Toggle::make('is_active')
                                ->label('Active Status')
                                ->default(true)
                                ->required(),
                        ]),
                        Grid::make(3)->schema([
                            TextInput::make('manager_name')
                                ->label('Branch Manager / OIC Name')
                                ->placeholder('e.g. Juan Santos Dela Cruz')
                                ->maxLength(255),
                            Select::make('manager_user_id')
                                ->label('Assigned User Account')
                                ->relationship('managerUser', 'name')
                                ->searchable()
                                ->preload()
                                ->placeholder('Select an option'),
                            TextInput::make('manager_email')
                                ->label('Manager Email Address')
                                ->email()
                                ->placeholder('e.g. manager@branch.com')
                                ->maxLength(255),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make('manager_phone_no')
                                ->label('Manager Contact No.')
                                ->placeholder('e.g. 0917-123-4567')
                                ->maxLength(50),
                            Textarea::make('address')
                                ->label('Complete Address')
                                ->placeholder('e.g. Street, City, Province, Zip Code')
                                ->rows(2),
                        ]),
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
                    ->label('Branch Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('manager_name')
                    ->label('Branch Manager / OIC')
                    ->description(fn (BranchOffice $record): ?string => $record->manager_email ?? $record->manager_phone_no)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('area')
                    ->label('Area')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('address')
                    ->label('Address')
                    ->searchable()
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
                Tables\Filters\SelectFilter::make('area')
                    ->label('Regional Area')
                    ->options([
                        'Area 1' => 'Area 1',
                        'Area 2' => 'Area 2',
                        'Area 3' => 'Area 3',
                    ]),
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
            'index' => Pages\ListBranchOffices::route('/'),
            'create' => Pages\CreateBranchOffice::route('/create'),
            'edit' => Pages\EditBranchOffice::route('/{record}/edit'),
        ];
    }
}
