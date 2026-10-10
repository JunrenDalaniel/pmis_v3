<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchOfficeResource\Pages;
use App\Models\BranchOffice;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
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

    // protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Branch Office Details')
                    ->description('Location, assigned regional area, and operational status')
                    ->icon('heroicon-o-building-office')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                            TextInput::make('branch_id')
                                ->label('Branch ID')
                                ->placeholder('e.g. BR-001')
                                ->prefixIcon('heroicon-o-identification')
                                ->default(function () {
                                    $nextId = (\App\Models\BranchOffice::max('id') ?? 0) + 1;
                                    return 'BR-' . str_pad((string) $nextId, 3, '0', STR_PAD_LEFT);
                                })
                                ->unique(ignoreRecord: true)
                                ->maxLength(50),
                            TextInput::make('name')
                                ->label('Branch Name')
                                ->placeholder('e.g. BUTUAN')
                                ->prefixIcon('heroicon-o-building-office')
                                ->required()
                                ->maxLength(255),
                            Select::make('area')
                                ->label('Regional Area Assignment')
                                ->prefixIcon('heroicon-o-map')
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
                Tables\Columns\TextColumn::make('branch_id')
                    ->label('Branch ID')
                    ->badge()
                    ->color('primary')
                    ->placeholder('—')
                    ->searchable()
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
                    static::getExportToExcelBulkAction(),
                ]),
            ]);
    }

    public static function getExportToExcelBulkAction(): BulkAction
    {
        return BulkAction::make('export_to_excel')
            ->label('Export to Excel')
            ->icon('heroicon-o-document-arrow-down')
            ->color('success')
            ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Branch Offices');

                $headers = [
                    'Branch ID',
                    'Branch Name',
                    'Regional Area',
                    'Branch Manager / OIC',
                    'Manager Contact No.',
                    'Manager Email Address',
                    'Complete Address',
                    'Status',
                ];

                $sheet->fromArray([$headers], null, 'A1');

                // Header styling: Emerald green, bold white text, centered
                $sheet->getStyle('A1:H1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                        'size' => 11,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '15803D'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                $row = 2;
                foreach ($records as $record) {
                    $dataRow = [
                        $record->branch_id ?? '—',
                        $record->name ?? '—',
                        $record->area ?? '—',
                        $record->manager_name ?? '—',
                        $record->manager_phone_no ?? '—',
                        $record->manager_email ?? '—',
                        $record->address ?? '—',
                        $record->is_active ? 'Active' : 'Inactive',
                    ];

                    $sheet->fromArray([$dataRow], null, "A{$row}", true);

                    // Center-align Branch ID, Area, and Status
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Alternating row background for clean readability
                    if ($row % 2 === 1) {
                        $sheet->getStyle("A{$row}:H{$row}")
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setRGB('F8FAFC');
                    }

                    $sheet->getRowDimension($row)->setRowHeight(20);
                    $row++;
                }

                $lastRow = $row - 1;
                if ($lastRow >= 2) {
                    $sheet->getStyle("A1:H{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'E2E8F0'],
                            ],
                        ],
                    ]);
                }

                foreach (range('A', 'H') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $filename = 'Branch_Offices_Export_' . now()->format('Ymd_His') . '.xlsx';

                return response()->streamDownload(function () use ($spreadsheet) {
                    $writer = new Xlsx($spreadsheet);
                    $writer->save('php://output');
                }, $filename, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]);
            })
            ->deselectRecordsAfterCompletion();
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
