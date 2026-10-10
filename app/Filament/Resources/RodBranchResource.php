<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RodBranchResource\Pages;
use App\Models\RodBranch;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use UnitEnum;

class RodBranchResource extends Resource
{
    protected static ?string $model = RodBranch::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string | UnitEnum | null $navigationGroup = 'System Administration';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'ROD Branches';

    protected static ?string $modelLabel = 'ROD Branch';

    protected static ?string $pluralModelLabel = 'ROD Branches';

    // protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Registry of Deeds Office Information')
                    ->description('Registry of Deeds office location name and active status')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('ROD Office Name')
                                ->placeholder('e.g. Butuan City')
                                ->required()
                                ->maxLength(255),
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
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('ROD Office Name')
                    ->searchable()
                    ->sortable(),
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
                    static::getExportToExcelBulkAction(),
                    DeleteBulkAction::make(),
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
                $sheet->setTitle('ROD Branches');

                $headers = [
                    'ID',
                    'ROD Office Name',
                    'Status',
                    'Date Created',
                ];

                $sheet->fromArray([$headers], null, 'A1');

                // Header styling: Emerald green, bold white text, centered
                $sheet->getStyle('A1:D1')->applyFromArray([
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
                $sheet->getRowDimension(1)->setRowHeight(28);

                $row = 2;
                foreach ($records as $record) {
                    $dataRow = [
                        $record->id,
                        $record->name ?? '',
                        $record->is_active ? 'Active' : 'Inactive',
                        $record->created_at ? $record->created_at->format('M d, Y') : '',
                    ];

                    $sheet->fromArray([$dataRow], null, "A{$row}", true);

                    // Center-align ID, Status, Date Created
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Alternating row background for clean readability
                    if ($row % 2 === 1) {
                        $sheet->getStyle("A{$row}:D{$row}")
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setRGB('F8FAFC');
                    }

                    $sheet->getRowDimension($row)->setRowHeight(22);
                    $row++;
                }

                $lastRow = $row - 1;
                if ($lastRow >= 2) {
                    $sheet->getStyle("A1:D{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'E2E8F0'],
                            ],
                        ],
                    ]);
                }

                foreach (range('A', 'D') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $filename = 'ROD_Branches_Export_' . now()->format('Ymd_His') . '.xlsx';

                return response()->streamDownload(function () use ($spreadsheet) {
                    $writer = new Xlsx($spreadsheet);
                    $writer->save('php://output');
                }, $filename, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]);
            });
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
            'index' => Pages\ListRodBranches::route('/'),
            'create' => Pages\CreateRodBranch::route('/create'),
            'edit' => Pages\EditRodBranch::route('/{record}/edit'),
        ];
    }
}
