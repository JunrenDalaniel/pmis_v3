<?php

namespace App\Filament\Pages;

use App\Models\BranchOffice;
use App\Models\LoanApplication;
use App\Services\MortgageTransmittalService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\RawJs;
use UnitEnum;

class MortgageTransmittalGeneratorPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-printer';

    protected static ?string $title = 'Mortgage Transmittal Generator';

    protected static ?string $navigationLabel = 'Transmittal Generator';

    protected static string | UnitEnum | null $navigationGroup = 'Reports & Analytics';

    protected static ?int $navigationSort = 8;

    protected static ?string $slug = 'transmittal-generator';

    protected string $view = 'filament.pages.transmittal.generator-page';

    public ?array $data = [];

    public function mount(): void
    {
        $user = auth()->user();
        $allowed = $user?->getAllowedBranchIds();
        $defaultBranch = (is_array($allowed) && count($allowed) > 0) ? $allowed[0] : $user?->branch_office_id;

        $this->form->fill([
            'transmittal_no'   => 'TR-' . strtoupper(date('Ymd')) . '-' . rand(1000, 9999),
            'transmittal_date' => now()->format('Y-m-d'),
            'branch_office_id' => $defaultBranch,
            'collateral_type'  => 'real_estate_mortgage',
            'checked_by'       => null,
            'approved_by'      => null,
            'courier_details'  => 'Liaison Officer / Hand Carried',
            'items'            => [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Transmittal Header & Scope')
                    ->description('Set transmittal control details, branch office, and collateral type')
                    ->icon('heroicon-o-identification')
                    ->collapsible()
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                            TextInput::make('transmittal_no')
                                ->label('Transmittal Control No.')
                                ->required()
                                ->extraInputAttributes(['class' => 'font-mono font-bold tracking-wider text-primary-600']),

                            Select::make('branch_office_id')
                                ->label('Originating Branch')
                                ->options(function () {
                                    $user = auth()->user();
                                    $allowed = $user?->getAllowedBranchIds();
                                    $q = BranchOffice::query();
                                    if (is_array($allowed) && ! empty($allowed)) {
                                        $q->whereIn('id', $allowed);
                                    }
                                    return $q->pluck('name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live(),

                            Select::make('collateral_type')
                                ->label('Collateral Category')
                                ->options([
                                    'real_estate_mortgage' => 'Real Estate Mortgage (REM)',
                                    'chattel_mortgage'      => 'Chattel Mortgage (CM)',
                                ])
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, $get) {
                                    // Update prefix of control no
                                    $prefix = $state === 'real_estate_mortgage' ? 'REM' : 'CM';
                                    $set('transmittal_no', 'TR-' . $prefix . '-' . strtoupper(date('Ymd')) . '-' . rand(1000, 9999));
                                }),

                            DatePicker::make('transmittal_date')
                                ->label('Transmittal Date')
                                ->required(),
                        ]),

                        Grid::make(['default' => 1, 'sm' => 3])->schema([
                            TextInput::make('checked_by')
                                ->label('Checked By (Bookkeeper)')
                                ->placeholder('type name of Bookkeeper')
                                ->required(),

                            TextInput::make('approved_by')
                                ->label('Approved By (Branch Manager)')
                                ->placeholder('type name of Manager')
                                ->required(),

                            TextInput::make('courier_details')
                                ->label('Courier / Delivery Handling')
                                ->placeholder('e.g. Liaison Officer / Hand Carried / LBC')
                                ->required(),
                        ]),
                    ]),

                Section::make('Enclosed Loan Documents & Statutory Fee Breakdown')
                    ->description('Select loan applications from your branch. Statutory fees (ROD/LTO, BIR DST, HO Fee, Misc, SPA) are auto-computed and CAN BE FREELY EDITED below.')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        Repeater::make('items')
                            ->label('Loan Records in Batch')
                            ->addActionLabel('Add Loan Application to Transmittal')
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => ($state['borrower_name'] ?? 'Loan Record') . (!empty($state['loan_amount']) ? ' — ₱' . number_format((float) str_replace(',', '', (string) $state['loan_amount']), 2) : ''))
                            ->schema([
                                Hidden::make('security_type')->default(fn ($get) => $get('../../collateral_type') === 'chattel_mortgage' ? 'CM' : 'REM'),
                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                                    Select::make('loan_application_id')
                                        ->label('Select Loan Application')
                                        ->placeholder('Search by borrower name or App #')
                                        ->searchable()
                                        ->preload()
                                        ->options(function ($get) {
                                            $branchId = $get('../../branch_office_id');
                                            $collateralType = $get('../../collateral_type');

                                            $q = LoanApplication::query()->with('member');
                                            if ($branchId) {
                                                $q->where('branch_office_id', $branchId);
                                            }
                                            if ($collateralType === 'real_estate_mortgage') {
                                                $q->where(fn ($sub) => $sub->where('collateral_type', 'like', '%real%')->orWhere('collateral_type', 'like', '%rem%'));
                                            } elseif ($collateralType === 'chattel_mortgage') {
                                                $q->where(fn ($sub) => $sub->where('collateral_type', 'like', '%chattel%')->orWhere('collateral_type', 'like', '%cm%'));
                                            }
                                            return $q->latest()->take(60)->get()->mapWithKeys(function ($l) {
                                                $label = ($l->application_no ?: 'No App#') . ' — ' . ($l->member?->full_name ?? 'No Borrower') . ' (₱' . number_format((float)$l->requested_amount, 2) . ')';
                                                return [$l->id => $label];
                                            });
                                        })
                                        ->live()
                                        ->afterStateUpdated(function ($state, callable $set, $get) {
                                            if (! $state) return;
                                            $loan = LoanApplication::with(['member', 'realEstateCollaterals', 'chattelCollaterals'])->find($state);
                                            if (! $loan) return;

                                            $isRem = $get('../../collateral_type') === 'real_estate_mortgage';
                                            $sec = $isRem ? 'REM' : 'CM';

                                            $bName = $loan->member?->full_name ?? '—';
                                            $owner = $isRem
                                                ? ($loan->realEstateCollaterals->first()?->title_owner_name ?: $bName)
                                                : ($loan->chattelCollaterals->first()?->owner_name ?: $bName);

                                            $isSpa = strcasecmp(trim((string) $owner), trim((string) $bName)) !== 0;

                                            $amt = (float) $loan->requested_amount;
                                            $fees = MortgageTransmittalService::computeFees($amt, $sec, false);

                                            $set('loan_account_no', $loan->loan_account_no ?: $loan->application_no);
                                            $set('borrower_name', $bName);
                                            $set('registered_owner', $owner);
                                            $set('loan_amount', number_format($amt, 2, '.', ','));
                                            $set('security_type', $sec);
                                            $set('date_granted', $loan->date_granted?->format('Y-m-d'));
                                            $set('maturity_date', $loan->maturity_date?->format('Y-m-d'));

                                            // Fees formatted as accounting numbers
                                            $set('ho_processing_fee', number_format((float) $fees['ho_processing_fee'], 2, '.', ','));
                                            $set('bir_dst_fee', number_format((float) $fees['bir_dst_fee'], 2, '.', ','));
                                            $set('rod_fee', number_format((float) $fees['rod_fee'], 2, '.', ','));
                                            $set('lto_fee', number_format((float) $fees['lto_fee'], 2, '.', ','));
                                            $set('misc_fee', number_format((float) $fees['misc_fee'], 2, '.', ','));
                                            $spa = $isRem ? (float) str_replace(',', '', (string) $get('spa_fee')) : 0.00;
                                            $set('spa_fee', number_format($spa > 0 ? $spa : 0, 2, '.', ','));
                                            $aff = $isRem ? (float) str_replace(',', '', (string) $get('affidavit_fee')) : 0.00;
                                            $set('affidavit_fee', number_format($aff > 0 ? $aff : 0, 2, '.', ','));
                                            $addl = $isRem ? (float) str_replace(',', '', (string) $get('additional_fee')) : 0.00;
                                            $set('additional_fee', number_format($addl > 0 ? $addl : 0, 2, '.', ','));
                                            $tot = (float)$fees['ho_processing_fee'] +
                                                   (float)$fees['bir_dst_fee'] +
                                                   (float)$fees['rod_fee'] +
                                                   (float)$fees['lto_fee'] +
                                                   (float)$fees['misc_fee'] +
                                                   ($spa > 0 ? $spa : 0) +
                                                   ($aff > 0 ? $aff : 0) +
                                                   ($addl > 0 ? $addl : 0);
                                            $set('total_expense', number_format($tot, 2, '.', ','));

                                            // Documents attached summary (left blank by default; sample shown as placeholder)
                                        }),

                                    TextInput::make('loan_account_no')
                                        ->label('Loan Account / App #')
                                        ->required(),

                                    TextInput::make('borrower_name')
                                        ->label("Borrower's Name")
                                        ->required(),

                                    TextInput::make('registered_owner')
                                        ->label('Registered Owner')
                                        ->helperText('Optional - registered title/ORCR owner if distinct'),
                                ]),

                                Grid::make(['default' => 1, 'sm' => 3])->schema([
                                    TextInput::make('loan_amount')
                                        ->label('Loan Amount (PHP)')
                                        ->mask(RawJs::make('$money($input)'))
                                        ->stripCharacters(',')
                                        ->placeholder('0.00')
                                        ->formatStateUsing(fn ($state) => ($state !== null && $state !== '') ? number_format((float) str_replace(',', '', (string) $state), 2, '.', ',') : null)
                                        ->prefix('₱')
                                        ->required()
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, callable $set, $get) {
                                            $amt = (float) str_replace(',', '', (string) ($state ?? 0));
                                            $sec = $get('security_type') ?: ($get('../../collateral_type') === 'chattel_mortgage' ? 'CM' : 'REM');
                                            $fees = MortgageTransmittalService::computeFees($amt, $sec, false);

                                            $set('ho_processing_fee', number_format((float) $fees['ho_processing_fee'], 2, '.', ','));
                                            $set('bir_dst_fee', number_format((float) $fees['bir_dst_fee'], 2, '.', ','));
                                            $set('rod_fee', number_format((float) $fees['rod_fee'], 2, '.', ','));
                                            $set('lto_fee', number_format((float) $fees['lto_fee'], 2, '.', ','));
                                            $set('misc_fee', number_format((float) $fees['misc_fee'], 2, '.', ','));
                                            if ($sec === 'CM') {
                                                $set('spa_fee', '0.00');
                                                $set('affidavit_fee', '0.00');
                                                $set('additional_fee', '0.00');
                                            }

                                            $parse = fn ($val) => (float) str_replace(',', '', (string) ($val ?? 0));
                                            $tot = (float) $fees['ho_processing_fee'] +
                                                   (float) $fees['bir_dst_fee'] +
                                                   (float) $fees['rod_fee'] +
                                                   (float) $fees['lto_fee'] +
                                                   (float) $fees['misc_fee'] +
                                                   ($sec === 'CM' ? 0.0 : ($parse($get('spa_fee')) + $parse($get('affidavit_fee')) + $parse($get('additional_fee'))));
                                            $set('total_expense', number_format($tot, 2, '.', ','));
                                        }),

                                    DatePicker::make('date_granted')
                                        ->label('Date Granted'),

                                    DatePicker::make('maturity_date')
                                        ->label('Maturity Date'),
                                ]),

                                Grid::make(['default' => 2, 'sm' => 2, 'md' => 4])->schema([
                                    TextInput::make('ho_processing_fee')
                                        ->label('HO Fee')
                                        ->mask(RawJs::make('$money($input)'))
                                        ->stripCharacters(',')
                                        ->placeholder('1,000.00')
                                        ->default(fn ($get) => ($get('security_type') === 'CM' || $get('../../collateral_type') === 'chattel_mortgage') ? '500.00' : '1,000.00')
                                        ->formatStateUsing(fn ($state) => ($state !== null && $state !== '') ? number_format((float) str_replace(',', '', (string) $state), 2, '.', ',') : '1,000.00')
                                        ->prefix('₱')
                                        ->helperText(fn ($get) => ($get('security_type') === 'CM' || $get('../../collateral_type') === 'chattel_mortgage') ? 'CM Rate: ₱500.00' : 'REM Rate: ₱1,000.00')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (callable $set, $get) {
                                            $parse = fn ($val) => (float) str_replace(',', '', (string) ($val ?? 0));
                                            $tot = $parse($get('ho_processing_fee')) + $parse($get('bir_dst_fee')) + $parse($get('rod_fee')) + $parse($get('lto_fee')) + $parse($get('misc_fee')) + $parse($get('spa_fee')) + $parse($get('affidavit_fee')) + $parse($get('additional_fee'));
                                            $set('total_expense', number_format($tot, 2, '.', ','));
                                        }),

                                    TextInput::make('bir_dst_fee')
                                        ->label('BIR DST')
                                        ->mask(RawJs::make('$money($input)'))
                                        ->stripCharacters(',')
                                        ->placeholder('0.00')
                                        ->formatStateUsing(fn ($state) => ($state !== null && $state !== '') ? number_format((float) str_replace(',', '', (string) $state), 2, '.', ',') : '0.00')
                                        ->prefix('₱')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (callable $set, $get) {
                                            $parse = fn ($val) => (float) str_replace(',', '', (string) ($val ?? 0));
                                            $tot = $parse($get('ho_processing_fee')) + $parse($get('bir_dst_fee')) + $parse($get('rod_fee')) + $parse($get('lto_fee')) + $parse($get('misc_fee')) + $parse($get('spa_fee')) + $parse($get('affidavit_fee')) + $parse($get('additional_fee'));
                                            $set('total_expense', number_format($tot, 2, '.', ','));
                                        }),

                                    TextInput::make('rod_fee')
                                        ->label('ROD Fee')
                                        ->mask(RawJs::make('$money($input)'))
                                        ->stripCharacters(',')
                                        ->placeholder('0.00')
                                        ->default(fn ($get) => ($get('security_type') === 'CM' || $get('../../collateral_type') === 'chattel_mortgage') ? '1,000.00' : '0.00')
                                        ->formatStateUsing(fn ($state) => ($state !== null && $state !== '') ? number_format((float) str_replace(',', '', (string) $state), 2, '.', ',') : '0.00')
                                        ->prefix('₱')
                                        ->helperText(fn ($get) => ($get('security_type') === 'CM' || $get('../../collateral_type') === 'chattel_mortgage') ? 'Fixed ₱1,000.00 (online registration payment)' : 'Estimated baseline — editable per actual ROD assessment')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (callable $set, $get) {
                                            $parse = fn ($val) => (float) str_replace(',', '', (string) ($val ?? 0));
                                            $tot = $parse($get('ho_processing_fee')) + $parse($get('bir_dst_fee')) + $parse($get('rod_fee')) + $parse($get('lto_fee')) + $parse($get('misc_fee')) + $parse($get('spa_fee')) + $parse($get('affidavit_fee')) + $parse($get('additional_fee'));
                                            $set('total_expense', number_format($tot, 2, '.', ','));
                                        }),

                                    TextInput::make('lto_fee')
                                        ->label('LTO Fee')
                                        ->mask(RawJs::make('$money($input)'))
                                        ->stripCharacters(',')
                                        ->placeholder('0.00')
                                        ->default(fn ($get) => ($get('security_type') === 'CM' || $get('../../collateral_type') === 'chattel_mortgage') ? '1,500.00' : '0.00')
                                        ->formatStateUsing(fn ($state) => ($state !== null && $state !== '') ? number_format((float) str_replace(',', '', (string) $state), 2, '.', ',') : '0.00')
                                        ->prefix('₱')
                                        ->helperText(fn ($get) => ($get('security_type') === 'CM' || $get('../../collateral_type') === 'chattel_mortgage') ? '₱1,500.00 / CR (Add ₱1,500 if with cancellation = ₱3,000.00)' : 'LTO not applicable for REM')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (callable $set, $get) {
                                            $parse = fn ($val) => (float) str_replace(',', '', (string) ($val ?? 0));
                                            $tot = $parse($get('ho_processing_fee')) + $parse($get('bir_dst_fee')) + $parse($get('rod_fee')) + $parse($get('lto_fee')) + $parse($get('misc_fee')) + $parse($get('spa_fee')) + $parse($get('affidavit_fee')) + $parse($get('additional_fee'));
                                            $set('total_expense', number_format($tot, 2, '.', ','));
                                        }),

                                    TextInput::make('misc_fee')
                                        ->label('Misc & Env.')
                                        ->mask(RawJs::make('$money($input)'))
                                        ->stripCharacters(',')
                                        ->placeholder('0.00')
                                        ->default(fn ($get) => ($get('security_type') === 'CM' || $get('../../collateral_type') === 'chattel_mortgage') ? '0.00' : '580.00')
                                        ->formatStateUsing(fn ($state) => ($state !== null && $state !== '') ? number_format((float) str_replace(',', '', (string) $state), 2, '.', ',') : '0.00')
                                        ->prefix('₱')
                                        ->helperText(fn ($get) => ($get('security_type') === 'CM' || $get('../../collateral_type') === 'chattel_mortgage') ? '₱0.00 (Online payment for CM)' : '₱580.00 standard (₱500 misc + ₱50 stamp + ₱30 env)')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (callable $set, $get) {
                                            $parse = fn ($val) => (float) str_replace(',', '', (string) ($val ?? 0));
                                            $tot = $parse($get('ho_processing_fee')) + $parse($get('bir_dst_fee')) + $parse($get('rod_fee')) + $parse($get('lto_fee')) + $parse($get('misc_fee')) + $parse($get('spa_fee')) + $parse($get('affidavit_fee')) + $parse($get('additional_fee'));
                                            $set('total_expense', number_format($tot, 2, '.', ','));
                                        }),

                                    TextInput::make('spa_fee')
                                        ->label('SPA Fee')
                                        ->mask(RawJs::make('$money($input)'))
                                        ->stripCharacters(',')
                                        ->default('0.00')
                                        ->nullable()
                                        ->placeholder('0.00')
                                        ->formatStateUsing(fn ($state) => ($state !== null && $state !== '') ? number_format((float) str_replace(',', '', (string) $state), 2, '.', ',') : '0.00')
                                        ->prefix('₱')
                                        ->helperText(fn ($get) => ($get('security_type') === 'CM' || $get('../../collateral_type') === 'chattel_mortgage') ? '₱0.00 (Wala nang SPA fee para sa CM)' : '₱626.32/doc (1 pc = ₱626.32 | 2 pcs = ₱1,252.64 | 3 pcs = ₱1,878.96)')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (callable $set, $get) {
                                            $parse = fn ($val) => (float) str_replace(',', '', (string) ($val ?? 0));
                                            $tot = $parse($get('ho_processing_fee')) + $parse($get('bir_dst_fee')) + $parse($get('rod_fee')) + $parse($get('lto_fee')) + $parse($get('misc_fee')) + $parse($get('spa_fee')) + $parse($get('affidavit_fee')) + $parse($get('additional_fee'));
                                            $set('total_expense', number_format($tot, 2, '.', ','));
                                        }),

                                    TextInput::make('affidavit_fee')
                                        ->label('Affidavit Fee')
                                        ->mask(RawJs::make('$money($input)'))
                                        ->stripCharacters(',')
                                        ->default('0.00')
                                        ->nullable()
                                        ->placeholder('0.00')
                                        ->formatStateUsing(fn ($state) => ($state !== null && $state !== '') ? number_format((float) str_replace(',', '', (string) $state), 2, '.', ',') : '0.00')
                                        ->prefix('₱')
                                        ->helperText(fn ($get) => ($get('security_type') === 'CM' || $get('../../collateral_type') === 'chattel_mortgage') ? '₱0.00 (Wala nang Affidavit fee para sa CM)' : '₱536.32/doc (1 pc = ₱536.32 | 2 pcs = ₱1,072.64 | 3 pcs = ₱1,608.96)')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (callable $set, $get) {
                                            $parse = fn ($val) => (float) str_replace(',', '', (string) ($val ?? 0));
                                            $tot = $parse($get('ho_processing_fee')) + $parse($get('bir_dst_fee')) + $parse($get('rod_fee')) + $parse($get('lto_fee')) + $parse($get('misc_fee')) + $parse($get('spa_fee')) + $parse($get('affidavit_fee')) + $parse($get('additional_fee'));
                                            $set('total_expense', number_format($tot, 2, '.', ','));
                                        }),

                                    TextInput::make('additional_fee')
                                        ->label("Add'l Exp.")
                                        ->mask(RawJs::make('$money($input)'))
                                        ->stripCharacters(',')
                                        ->default('0.00')
                                        ->nullable()
                                        ->placeholder('0.00')
                                        ->formatStateUsing(fn ($state) => ($state !== null && $state !== '') ? number_format((float) str_replace(',', '', (string) $state), 2, '.', ',') : '0.00')
                                        ->prefix('₱')
                                        ->helperText(fn ($get) => ($get('security_type') === 'CM' || $get('../../collateral_type') === 'chattel_mortgage') ? '₱0.00 (Wala nang add\'l fee para sa CM)' : 'Food / fuel / travel / contingency')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (callable $set, $get) {
                                            $parse = fn ($val) => (float) str_replace(',', '', (string) ($val ?? 0));
                                            $tot = $parse($get('ho_processing_fee')) + $parse($get('bir_dst_fee')) + $parse($get('rod_fee')) + $parse($get('lto_fee')) + $parse($get('misc_fee')) + $parse($get('spa_fee')) + $parse($get('affidavit_fee')) + $parse($get('additional_fee'));
                                            $set('total_expense', number_format($tot, 2, '.', ','));
                                        }),

                                    TextInput::make('total_expense')
                                        ->label('Total Expense')
                                        ->mask(RawJs::make('$money($input)'))
                                        ->stripCharacters(',')
                                        ->default(fn ($get) => ($get('security_type') === 'CM' || $get('../../collateral_type') === 'chattel_mortgage') ? '3,000.00' : '1,580.00')
                                        ->placeholder('0.00')
                                        ->formatStateUsing(fn ($state) => ($state !== null && $state !== '') ? number_format((float) str_replace(',', '', (string) $state), 2, '.', ',') : '0.00')
                                        ->prefix('₱')
                                        ->readOnly()
                                        ->extraInputAttributes(['class' => 'font-bold text-primary-600 bg-gray-50 dark:bg-white/[0.05]']),
                                ]),

                                Textarea::make('documents_attached')
                                    ->label('Enclosed Physical Documents Checklist')
                                    ->placeholder("e.g. 4 SETS REM, ORIG TCT/OCT W/ TITLE # 52234234, CTC TAX DEC, TAX CLEARANCE, SPA, PHOTOCOPY VALID ID'S, PN & SWORN UNDERTAKING")
                                    ->helperText('Optional: Leave empty or enter customized remarks / enclosed documents checklist')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function printTransmittal(): void
    {
        $data = $this->form->getState();
        $items = $data['items'] ?? [];
        if (empty($items)) {
            Notification::make()
                ->title('Records Required')
                ->body('Please add at least one loan application before printing.')
                ->warning()
                ->send();
            return;
        }

        $branch = BranchOffice::find($data['branch_office_id'] ?? null);
        $data['branch_name'] = $branch?->name ?? 'ALL BRANCHES';

        session()->put('transmittal_preview_data', $data);

        $url = route('transmittals.session-print');
        $this->js(<<<JS
            let printFrame = document.getElementById('pmis-transmittal-print-frame');
            if (!printFrame) {
                printFrame = document.createElement('iframe');
                printFrame.id = 'pmis-transmittal-print-frame';
                printFrame.style.position = 'fixed';
                printFrame.style.right = '0';
                printFrame.style.bottom = '0';
                printFrame.style.width = '0';
                printFrame.style.height = '0';
                printFrame.style.border = '0';
                printFrame.style.visibility = 'hidden';
                document.body.appendChild(printFrame);
            }
            printFrame.onload = function() {
                setTimeout(function() {
                    try {
                        printFrame.contentWindow.focus();
                        printFrame.contentWindow.print();
                    } catch (e) {
                        console.error('Direct print error:', e);
                    }
                }, 250);
            };
            printFrame.src = '{$url}?t=' + Date.now();
        JS);
    }

    public function exportExcel()
    {
        $data = $this->form->getState();
        $items = $data['items'] ?? [];
        if (empty($items)) {
            Notification::make()
                ->title('Records Required')
                ->body('Please add at least one loan application before exporting.')
                ->warning()
                ->send();
            return;
        }

        $branch = BranchOffice::find($data['branch_office_id'] ?? null);
        $branchName = $branch?->name ?? 'ALL BRANCHES';

        $options = [
            'transmittal_no'   => $data['transmittal_no'] ?? null,
            'transmittal_date' => $data['transmittal_date'] ?? now()->format('Y-m-d'),
            'checked_by'       => $data['checked_by'] ?? '',
            'approved_by'      => $data['approved_by'] ?? '',
            'courier_details'  => $data['courier_details'] ?? 'Liaison Officer / Hand Carried',
            'branch_name'      => $branchName,
            'collateral_type'  => $data['collateral_type'] ?? 'real_estate_mortgage',
        ];

        return MortgageTransmittalService::generateExcelFromLoans($items, $options);
    }
}