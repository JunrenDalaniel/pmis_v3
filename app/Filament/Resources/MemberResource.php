<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MemberResource\Pages;
use App\Filament\Resources\MemberResource\RelationManagers;
use App\Models\Member;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
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
        return ['member_no', 'cid', 'first_name', 'last_name', 'suffix', 'email', 'phone', 'loanApplications.application_no'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string | \Illuminate\Contracts\Support\Htmlable
    {
        $idParts = array_filter([$record->member_no, $record->cid ? "CID: {$record->cid}" : null]);
        return $record->full_name . (! empty($idParts) ? ' (' . implode(' • ', $idParts) . ')' : '');
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        $details = [
            'Branch' => $record->branchOffice?->name,
            'CID'    => $record->cid,
        ];

        $loans = $record->loanApplications;

        if ($loans && $loans->isNotEmpty()) {
            if ($loans->count() === 1) {
                $loan = $loans->first();
                $collateral = match (true) {
                    str_contains(strtolower((string) $loan->collateral_type), 'chattel') || str_contains(strtolower((string) $loan->collateral_type), 'cm') => 'Chattel Mortgage (CM)',
                    str_contains(strtolower((string) $loan->collateral_type), 'real_estate') || str_contains(strtolower((string) $loan->collateral_type), 'rem') => 'Real Estate Mortgage (REM)',
                    default => ucwords(str_replace('_', ' ', (string) $loan->collateral_type)),
                };
                $details['Collateral Type'] = $collateral;
                $details['Loan Amount'] = '₱' . number_format((float) $loan->requested_amount, 2);
            } else {
                $collateralTypes = $loans->map(function ($loan) {
                    return match (true) {
                        str_contains(strtolower((string) $loan->collateral_type), 'chattel') || str_contains(strtolower((string) $loan->collateral_type), 'cm') => 'CM',
                        str_contains(strtolower((string) $loan->collateral_type), 'real_estate') || str_contains(strtolower((string) $loan->collateral_type), 'rem') => 'REM',
                        default => ucwords(str_replace('_', ' ', (string) $loan->collateral_type)),
                    };
                })->unique()->implode(' / ');

                $totalAmount = $loans->sum('requested_amount');
                $details['Collateral Type'] = $collateralTypes;
                $details['Loan Amount'] = '₱' . number_format((float) $totalAmount, 2) . ' (' . $loans->count() . ' Loans)';
            }
        } else {
            $details['Collateral Type'] = 'None';
            $details['Loan Amount'] = 'None';
        }

        if ($record->phone) {
            $details['Contact'] = $record->phone;
        }

        return array_filter($details, fn ($val) => filled($val));
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): string
    {
        return static::getUrl('view', ['record' => $record]);
    }

    public static function getGlobalSearchEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['branchOffice', 'loanApplications']);
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
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 5])->schema([
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
                            TextInput::make('cid')
                                ->label('CID')
                                ->placeholder('e.g. 100234')
                                ->prefixIcon('heroicon-o-hashtag')
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
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 5])->schema([
                            TextEntry::make('member_no')
                                ->label('Member ID')
                                ->badge()
                                ->color('primary')
                                ->icon('heroicon-o-hashtag')
                                ->copyable(),

                            TextEntry::make('cid')
                                ->label('CID')
                                ->badge()
                                ->color('info')
                                ->icon('heroicon-o-hashtag')
                                ->copyable()
                                ->placeholder('—'),

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
                Tables\Columns\TextColumn::make('cid')
                    ->label('CID')
                    ->placeholder('—')
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
                    static::getExportToExcelBulkAction(),
                    static::getImportExcelBulkAction(),
                ]),
            ]);
    }

    public static function getImportModalSchema(): array
    {
        return [
            Placeholder::make('template_guide')
                ->hiddenLabel()
                ->content(new HtmlString('
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs space-y-2">
                        <div class="font-semibold text-slate-800 dark:text-slate-200 flex items-center justify-between flex-wrap gap-2">
                            <span>📋 Excel Import Format Guide</span>
                            <a href="' . route('members.download-template') . '" class="text-emerald-600 dark:text-emerald-400 font-bold hover:underline inline-flex items-center gap-1" download>
                                📥 Download Sample Template (.xlsx)
                            </a>
                        </div>
                        <p class="text-slate-600 dark:text-slate-400">
                            Required columns: <b>First Name</b> and <b>Last Name</b>. If Member ID is left blank, the system will automatically generate sequential IDs (e.g. <code>API-2026-XXXXX</code>).
                        </p>
                    </div>
                ')),

            FileUpload::make('excel_file')
                ->label('Choose Excel File (.xlsx, .xls, .csv)')
                ->disk('local')
                ->directory('temp-member-imports')
                ->acceptedFileTypes([
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-excel',
                    'text/csv',
                    'application/csv',
                ])
                ->required()
                ->columnSpanFull(),
        ];
    }

    public static function processExcelImport(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (empty($rows) || count($rows) < 2) {
            return ['success' => 0, 'skipped' => 0, 'error' => 'The uploaded file is empty or missing data rows.'];
        }

        $headerRow = $rows[1] ?? [];
        $colMap = [];
        foreach ($headerRow as $colLetter => $headerText) {
            $clean = strtolower(trim((string) $headerText));
            if (str_contains($clean, 'member') && (str_contains($clean, 'id') || str_contains($clean, 'no'))) {
                $colMap['member_no'] = $colLetter;
            } elseif (str_contains($clean, 'cid') || str_contains($clean, 'client')) {
                $colMap['cid'] = $colLetter;
            } elseif (str_contains($clean, 'first')) {
                $colMap['first_name'] = $colLetter;
            } elseif (str_contains($clean, 'middle')) {
                $colMap['middle_name'] = $colLetter;
            } elseif (str_contains($clean, 'last')) {
                $colMap['last_name'] = $colLetter;
            } elseif (str_contains($clean, 'suffix')) {
                $colMap['suffix'] = $colLetter;
            } elseif (str_contains($clean, 'birth') || str_contains($clean, 'dob')) {
                $colMap['birth_date'] = $colLetter;
            } elseif (str_contains($clean, 'sex') || str_contains($clean, 'gender')) {
                $colMap['sex'] = $colLetter;
            } elseif (str_contains($clean, 'civil')) {
                $colMap['civil_status'] = $colLetter;
            } elseif (str_contains($clean, 'tin')) {
                $colMap['tin'] = $colLetter;
            } elseif (str_contains($clean, 'sss') || str_contains($clean, 'gsis')) {
                $colMap['sss_gsis'] = $colLetter;
            } elseif (str_contains($clean, 'email')) {
                $colMap['email'] = $colLetter;
            } elseif (str_contains($clean, 'phone') || str_contains($clean, 'mobile') || str_contains($clean, 'contact')) {
                $colMap['phone'] = $colLetter;
            } elseif (str_contains($clean, 'address')) {
                $colMap['address'] = $colLetter;
            } elseif (str_contains($clean, 'branch')) {
                $colMap['branch'] = $colLetter;
            }
        }

        // Positional defaults if headers are not detected
        $colMap['member_no']   = $colMap['member_no'] ?? 'A';
        $colMap['cid']         = $colMap['cid'] ?? 'B';
        $colMap['first_name']  = $colMap['first_name'] ?? 'C';
        $colMap['middle_name'] = $colMap['middle_name'] ?? 'D';
        $colMap['last_name']   = $colMap['last_name'] ?? 'E';
        $colMap['suffix']      = $colMap['suffix'] ?? 'F';
        $colMap['birth_date']  = $colMap['birth_date'] ?? 'G';
        $colMap['sex']         = $colMap['sex'] ?? 'H';
        $colMap['civil_status']= $colMap['civil_status'] ?? 'I';
        $colMap['tin']         = $colMap['tin'] ?? 'J';
        $colMap['email']       = $colMap['email'] ?? 'K';
        $colMap['phone']       = $colMap['phone'] ?? 'L';
        $colMap['branch']      = $colMap['branch'] ?? 'M';
        $colMap['address']     = $colMap['address'] ?? 'N';

        $cleanVal = function ($val) {
            $str = trim((string) ($val ?? ''));
            if ($str === '' || $str === '—' || $str === '-' || strtolower($str) === 'n/a' || strtolower($str) === 'null') {
                return null;
            }
            return $str;
        };

        $successCount = 0;
        $skippedCount = 0;
        $allBranches = \App\Models\BranchOffice::all();
        $nextAutoMemberId = (Member::max('id') ?? 0) + 1;

        for ($r = 2; $r <= count($rows); $r++) {
            $row = $rows[$r] ?? [];
            $firstName = $cleanVal($row[$colMap['first_name']] ?? null);
            $lastName = $cleanVal($row[$colMap['last_name']] ?? null);

            if (! $firstName || ! $lastName) {
                $skippedCount++;
                continue;
            }

            $middleName = $cleanVal($row[$colMap['middle_name']] ?? null);
            $suffix = $cleanVal($row[$colMap['suffix']] ?? null);
            $cid = $cleanVal($row[$colMap['cid']] ?? null);
            $memberNo = $cleanVal($row[$colMap['member_no']] ?? null) ?? '';

            // Parse Date of Birth
            $rawDob = $cleanVal($row[$colMap['birth_date']] ?? null);
            $birthDate = null;
            if (filled($rawDob)) {
                try {
                    if (is_numeric($rawDob)) {
                        $birthDate = ExcelDate::excelToDateTimeObject((float) $rawDob)->format('Y-m-d');
                    } else {
                        $birthDate = Carbon::parse($rawDob)->format('Y-m-d');
                    }
                } catch (\Throwable $e) {
                    $birthDate = null;
                }
            }

            // Sex
            $sexRaw = $cleanVal($row[$colMap['sex']] ?? null) ?? '';
            $sex = match (true) {
                str_starts_with(strtolower($sexRaw), 'm') => 'Male',
                str_starts_with(strtolower($sexRaw), 'f') => 'Female',
                default => null,
            };

            // Civil Status
            $civilRaw = $cleanVal($row[$colMap['civil_status']] ?? null) ?? '';
            $civilStatus = match (true) {
                str_contains(strtolower($civilRaw), 'sing') => 'Single',
                str_contains(strtolower($civilRaw), 'marr') => 'Married',
                str_contains(strtolower($civilRaw), 'wid')  => 'Widowed',
                str_contains(strtolower($civilRaw), 'sep')  => 'Separated',
                default => $civilRaw ?: null,
            };

            $tin = $cleanVal($row[$colMap['tin']] ?? null);
            $email = $cleanVal($row[$colMap['email']] ?? null);
            $phone = $cleanVal($row[$colMap['phone']] ?? null);
            $address = $cleanVal($row[$colMap['address']] ?? null);

            // Branch matching
            $branchRaw = $cleanVal($row[$colMap['branch']] ?? null) ?? '';
            $branchOfficeId = null;
            if ($branchRaw !== '') {
                $branch = $allBranches->first(function ($b) use ($branchRaw) {
                    return stripos($b->name, $branchRaw) !== false || (string) $b->branch_id === (string) $branchRaw;
                });
                if ($branch) {
                    $branchOfficeId = $branch->id;
                }
            }

            // Auto-generate or match Member No
            if ($memberNo === '') {
                $existing = null;
                if ($cid) {
                    $existing = Member::where('cid', $cid)->first();
                }
                if (! $existing && $birthDate) {
                    $existing = Member::where('first_name', $firstName)
                        ->where('last_name', $lastName)
                        ->where('birth_date', $birthDate)
                        ->first();
                }

                if ($existing) {
                    $memberNo = $existing->member_no;
                } else {
                    $year = date('Y');
                    do {
                        $memberNo = 'API-' . $year . '-' . str_pad((string) $nextAutoMemberId, 5, '0', STR_PAD_LEFT);
                        $nextAutoMemberId++;
                    } while (Member::where('member_no', $memberNo)->exists());
                }
            }

            Member::updateOrCreate(
                ['member_no' => $memberNo],
                array_filter([
                    'cid' => $cid,
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'last_name' => $lastName,
                    'suffix' => $suffix,
                    'birth_date' => $birthDate,
                    'sex' => $sex,
                    'civil_status' => $civilStatus,
                    'tin' => $tin,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'branch_office_id' => $branchOfficeId,
                    'is_active' => true,
                ], fn ($v) => $v !== null)
            );

            $successCount++;
        }

        return [
            'success' => $successCount,
            'skipped' => $skippedCount,
            'error'   => null,
        ];
    }

    public static function canImportExcel(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        // Disable Import Excel for Mortgage Document Specialist
        if ($user->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist']) || $user->role?->code === 'mortgage_document_specialist') {
            return false;
        }

        return true;
    }

    public static function getImportExcelAction(): Action
    {
        return Action::make('import_excel')
            ->label('Import Excel')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('info')
            ->visible(fn () => static::canImportExcel())
            ->modalHeading('Import Members from Excel')
            ->modalDescription('Upload an Excel spreadsheet to batch import members.')
            ->modalSubmitActionLabel('Import Records')
            ->schema(static::getImportModalSchema())
            ->action(function (array $data) {
                $filePath = Storage::disk('local')->path($data['excel_file']);
                $result = static::processExcelImport($filePath);
                Storage::disk('local')->delete($data['excel_file']);

                if ($result['error']) {
                    Notification::make()
                        ->title('Import Failed')
                        ->body($result['error'])
                        ->danger()
                        ->send();
                    return;
                }

                Notification::make()
                    ->title('✅ Members Imported Successfully')
                    ->body("Imported / Updated {$result['success']} member record(s)" . ($result['skipped'] > 0 ? " ({$result['skipped']} empty rows skipped)" : ''))
                    ->success()
                    ->send();
            });
    }

    public static function getImportExcelBulkAction(): BulkAction
    {
        return BulkAction::make('import_excel_bulk')
            ->label('Import Excel')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('info')
            ->visible(fn () => static::canImportExcel())
            ->modalHeading('Import Members from Excel')
            ->modalDescription('Upload an Excel spreadsheet to batch import members.')
            ->modalSubmitActionLabel('Import Records')
            ->schema(static::getImportModalSchema())
            ->action(function (array $data) {
                $filePath = Storage::disk('local')->path($data['excel_file']);
                $result = static::processExcelImport($filePath);
                Storage::disk('local')->delete($data['excel_file']);

                if ($result['error']) {
                    Notification::make()
                        ->title('Import Failed')
                        ->body($result['error'])
                        ->danger()
                        ->send();
                    return;
                }

                Notification::make()
                    ->title('✅ Members Imported Successfully')
                    ->body("Imported / Updated {$result['success']} member record(s)" . ($result['skipped'] > 0 ? " ({$result['skipped']} empty rows skipped)" : ''))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
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
                $sheet->setTitle('Members');

                $headers = [
                    'Member ID',
                    'CID',
                    'First Name',
                    'Middle Name',
                    'Last Name',
                    'Suffix',
                    'Date of Birth',
                    'Sex',
                    'Civil Status',
                    'TIN',
                    'Email Address',
                    'Phone Number',
                    'Assigned Branch',
                    'Complete Address',
                ];

                $sheet->fromArray([$headers], null, 'A1');

                // Header styling: Emerald green, bold white text, centered
                $sheet->getStyle('A1:N1')->applyFromArray([
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

                $records->loadMissing('branchOffice');
                $row = 2;
                foreach ($records as $record) {
                    $dataRow = [
                        $record->member_no ?? '',
                        $record->cid ?? '',
                        $record->first_name ?? '',
                        $record->middle_name ?? '',
                        $record->last_name ?? '',
                        $record->suffix ?? '',
                        $record->birth_date ? $record->birth_date->format('Y-m-d') : '',
                        $record->sex ?? '',
                        $record->civil_status ?? '',
                        $record->tin ?? '',
                        $record->email ?? '',
                        $record->phone ?? '',
                        $record->branchOffice?->name ?? '',
                        $record->address ?? '',
                    ];

                    $sheet->fromArray([$dataRow], null, "A{$row}", true);

                    // Center-align IDs, dates, sex, status
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("M{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Alternating row background for clean readability
                    if ($row % 2 === 1) {
                        $sheet->getStyle("A{$row}:N{$row}")
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
                    $sheet->getStyle("A1:N{$lastRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'E2E8F0'],
                            ],
                        ],
                    ]);
                }

                foreach (range('A', 'N') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $filename = 'Members_Export_' . now()->format('Ymd_His') . '.xlsx';

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
