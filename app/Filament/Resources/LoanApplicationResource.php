<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoanApplicationResource\Pages;
use App\Filament\Concerns\HandlesApprovalWorkflow;
use App\Models\LoanApplication;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use ZipArchive;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class LoanApplicationResource extends Resource
{
    protected static ?string $model = LoanApplication::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static string | UnitEnum | null $navigationGroup = 'Loan Operations';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Released Loans';

    protected static ?string $modelLabel = 'Released Loan';

    protected static ?string $pluralModelLabel = 'Released Loans';

    // protected static ?string $recordTitleAttribute = 'application_no';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where('approval_status', 'Approved')
            ->with(['member', 'branchOffice', 'chattelCollaterals', 'realEstateCollaterals']);

        $user = auth()->user();
        if ($user) {
            $allowedBranches = $user->getAllowedBranchIds();
            if (is_array($allowedBranches)) {
                $query->whereIn('branch_office_id', $allowedBranches);
            }
        }

        return $query;
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['member', 'branchOffice', 'chattelCollaterals', 'realEstateCollaterals']);

        $user = auth()->user();
        if ($user) {
            $allowedBranches = $user->getAllowedBranchIds();
            if (is_array($allowedBranches)) {
                $query->whereIn('branch_office_id', $allowedBranches);
            }
        }

        return $query;
    }

    // public static function getGloballySearchableAttributes(): array
    // {
    //     return ['application_no', 'member.first_name', 'member.last_name', 'member.member_no'];
    // }

    public static function getCachedMember(?int $memberId): ?\App\Models\Member
    {
        if (! $memberId) {
            return null;
        }
        static $memberCache = [];
        if (! array_key_exists($memberId, $memberCache)) {
            $memberCache[$memberId] = \App\Models\Member::with('branchOffice')->find($memberId);
        }
        return $memberCache[$memberId];
    }

    public static function getFormSections(): array
    {
        return [
            // SECTION 1: Member Selection & Full Member Profile Display
            'member_section' => Section::make('Borrower Member Selection')
                ->description('Select member borrower and view complete personal profile')
                ->icon('heroicon-o-user')
                ->schema([
                    Select::make('member_id')
                        ->label('Select Member')
                        ->relationship('member', 'last_name', function ($query) {
                            $user = auth()->user();
                            if ($user) {
                                $allowedBranches = $user->getAllowedBranchIds();
                                if (is_array($allowedBranches)) {
                                    $query->whereIn('branch_office_id', $allowedBranches);
                                }
                            }
                            return $query;
                        })
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name . ($record->member_no ? " ({$record->member_no})" : '') . ($record->cid ? " [CID: {$record->cid}]" : ''))
                        ->searchable(['member_no', 'cid', 'first_name', 'last_name', 'middle_name'])
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if (filled($state)) {
                                $member = \App\Models\Member::find($state);
                                if ($member && $member->branch_office_id) {
                                    $set('branch_office_id', $member->branch_office_id);
                                }
                            }
                        })
                        ->createOptionForm([
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
                                    ->unique('members', 'member_no'),
                                TextInput::make('cid')
                                    ->label('CID')
                                    ->placeholder('e.g. 100234')
                                    ->prefixIcon('heroicon-o-hashtag')
                                    ->maxLength(255),
                                TextInput::make('first_name')
                                    ->label('First Name')
                                    ->placeholder('e.g. Juan')
                                    ->prefixIcon('heroicon-o-user')
                                    ->required(),
                                TextInput::make('middle_name')
                                    ->label('Middle Name')
                                    ->placeholder('e.g. Santos')
                                    ->prefixIcon('heroicon-o-user'),
                            ]),
                            Grid::make(3)->schema([
                                TextInput::make('last_name')
                                    ->label('Last Name')
                                    ->placeholder('e.g. Dela Cruz')
                                    ->prefixIcon('heroicon-o-user')
                                    ->required(),
                                TextInput::make('suffix')
                                    ->label('Suffix')
                                    ->placeholder('e.g. Jr., Sr.')
                                    ->prefixIcon('heroicon-o-user-circle'),
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
                            Grid::make(3)->schema([
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
                                TextInput::make('ppsr_account_no')
                                    ->label('PPSR Account No.')
                                    ->placeholder('e.g. PPSR-USR-12345')
                                    ->prefixIcon('heroicon-o-shield-check')
                                    ->helperText('Optional: For Chattel Mortgage.')
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->label('Email Address')
                                    ->placeholder('e.g. member@example.com')
                                    ->prefixIcon('heroicon-o-envelope')
                                    ->email()
                                    ->unique('members', 'email'),
                                TextInput::make('phone')
                                    ->label('Phone Number')
                                    ->placeholder('e.g. 09171234567')
                                    ->prefixIcon('heroicon-o-phone')
                                    ->tel(),
                            ]),
                            Textarea::make('address')
                                ->label('Complete Address')
                                ->placeholder('e.g. Street, Barangay, City, Province')
                                ->rows(2)
                                ->columnSpanFull(),
                        ])
                        ->createOptionModalHeading('Add New Member'),

                    // Display Modern Borrower Profile Card
                    ViewField::make('member_profile_card')
                        ->view('filament.components.borrower-profile-card')
                        ->visible(fn ($get) => filled($get('member_id')))
                        ->columnSpanFull(),
                ]),

            // SECTION 2A: Financials & Identifiers
            'financials_section' => Section::make('Loan Financials & Term Details')
                ->description('Loan reference numbers, requested amount, and repayment schedule')
                ->icon('heroicon-o-banknotes')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('application_no')
                            ->label('Application ID')
                            ->placeholder('e.g. API-LA-2026-00008')
                            ->default(function () {
                                $year = date('Y');
                                $nextId = (\App\Models\LoanApplication::max('id') ?? 0) + 1;
                                return 'API-LA-' . $year . '-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
                            })
                            ->disabled()
                            ->dehydrated()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->suffixAction(
                                Action::make('copyAppId')
                                    ->icon('heroicon-m-clipboard-document')
                                    ->color('gray')
                                    ->tooltip('Copy Application ID')
                                    ->alpineClickHandler('window.navigator.clipboard.writeText($wire.get("data.application_no")); new FilamentNotification().title("Application ID copied to clipboard!").success().send();')
                            ),
                        Select::make('branch_office_id')
                            ->label('Processing OIC Branch')
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
                        TextInput::make('requested_amount')
                            ->label(fn ($record) => in_array($record?->approval_status, ['Approved', 'Branch Updated'])
                                ? 'Loan Amount (₱)'
                                : 'Requested Loan Amount (₱)')
                            ->helperText(fn ($record) => in_array($record?->approval_status, ['Approved', 'Branch Updated'])
                                ? 'Official approved loan amount granted to borrower'
                                : 'Initial loan amount requested by borrower for assessment')
                            ->prefix('₱')
                            ->placeholder('0.00')
                            ->live(onBlur: true)
                            ->formatStateUsing(fn ($state) => filled($state) ? number_format((float) str_replace(',', '', (string) $state), 2, '.', ',') : null)
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? (float) str_replace(',', '', (string) $state) : null)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (filled($state)) {
                                    $numeric = (float) str_replace(',', '', (string) $state);
                                    $set('requested_amount', number_format($numeric, 2, '.', ','));
                                }
                            })
                            ->required(),
                    ]),

                    Grid::make(3)->schema([
                        TextInput::make('loan_account_no')
                            ->label('Loan Account Number')
                            ->placeholder('e.g. LN-ACCT-2026-00123')
                            ->maxLength(255)
                            ->suffixAction(
                                Action::make('copyAccountNo')
                                    ->icon('heroicon-m-clipboard-document')
                                    ->color('gray')
                                    ->tooltip('Copy Account Number')
                                    ->alpineClickHandler('window.navigator.clipboard.writeText($wire.get("data.loan_account_no")); new FilamentNotification().title("Account Number copied to clipboard!").success().send();')
                            ),
                        DatePicker::make('date_granted')
                            ->label('Date Granted')
                            ->native(false)
                            ->displayFormat('F d, Y')
                            ->live()
                            ->maxDate(now()->addYear()),
                        DatePicker::make('maturity_date')
                            ->label('Maturity Date')
                            ->native(false)
                            ->displayFormat('F d, Y')
                            ->minDate(fn ($get) => $get('date_granted') ?: null)
                            ->maxDate(now()->addYears(15))
                            ->afterOrEqual('date_granted')
                            ->helperText('Must be on or after Date Granted.'),
                    ]),

                    Grid::make(1)->schema([
                        Select::make('collateral_type')
                            ->label('Mortgage Security Type')
                            ->placeholder('Select mortgage security type')
                            ->options([
                                'real_estate_mortgage' => 'Real Estate Mortgage (REM)',
                                'rem_certification' => 'Real Estate Mortgage Certification',
                                'rem_amendment' => 'Amendment Of Real Estate Mortgage',
                                'chattel_mortgage' => 'Chattel Mortgage (CM)',
                                'cm_certification' => 'Chattel Mortgage Certification',
                                'cm_amendment' => 'Amendment Of Chattel Mortgage (Requires Memorandum Approval)',
                            ])
                            ->disableOptionWhen(fn (string $value): bool => $value === 'cm_amendment')
                            ->native(false)
                            ->disabled(fn (string $operation, $record) => $operation === 'edit' && filled($record?->collateral_type))
                            ->dehydrated()
                            ->required()
                            ->live()
                            ->suffixAction(
                                Action::make('viewMortgageGuidelines')
                                    ->icon('heroicon-m-information-circle')
                                    ->color('primary')
                                    ->tooltip('View Documentary Guidelines (Slide View)')
                                    ->modalHeading('Official Mortgage Documentary Guidelines')
                                    ->modalDescription('Guide on mandatory documentary requirements prior to acceptance and endorsement of mortgage applications.')
                                    ->modalContent(fn ($get) => view('filament.modals.mortgage-guidelines-modal', [
                                        'collateralType' => $get('collateral_type'),
                                    ]))
                                    ->modalSubmitAction(false)
                                    ->modalCancelActionLabel('Close')
                                    ->slideOver()
                                    ->modalWidth('6xl')
                            ),
                    ]),
                ]),

            // SECTION 2B: Collateral Quick Snapshot
            'collateral_summary_section' => Section::make('Registered Collateral Snapshot')
                ->description('Active collaterals linked to this loan application')
                ->icon('heroicon-o-shield-check')
                ->visible(fn ($record) => filled($record?->id))
                ->collapsible()
                ->schema([
                    Placeholder::make('collateral_snapshot_content')
                        ->hiddenLabel()
                        ->content(function ($record) {
                            if (! $record) return null;
                            $chattels = $record->chattelCollaterals;
                            $rems = $record->realEstateCollaterals;

                            if ($chattels->isEmpty() && $rems->isEmpty()) {
                                return new \Illuminate\Support\HtmlString("
                                    <div class='text-xs text-gray-500 py-1.5 italic'>
                                        No collateral recorded yet. You can add collaterals in the <b>Collateral Information</b> tab.
                                    </div>
                                ");
                            }

                            $html = "<div class='space-y-3 text-xs'>";
                            foreach ($chattels as $c) {
                                $plate = e($c->plate_no ?: 'No Plate');
                                $desc = e($c->vehicle_description ?: '—');
                                $year = e($c->year_model ?: '—');
                                $engine = e($c->engine_no ?: '—');
                                $chassis = e($c->chassis_no ?: '—');
                                $mvFile = e($c->mv_file_no ?: '—');
                                $renDate = $c->registration_renewal_date ? $c->registration_renewal_date->format('F d, Y') : 'No Date';
                                $encumbered = e($c->encumbered_to ?: 'NEW');

                                $html .= "
                                    <div class='rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/60 p-4 shadow-sm text-xs space-y-2 mb-3'>
                                        <div class='flex items-center justify-between pb-2 border-b border-gray-100 dark:border-gray-800'>
                                            <span class='text-gray-500 dark:text-gray-400'>LTO Renewal: <strong class='text-gray-900 dark:text-gray-100 font-semibold'>{$renDate}</strong></span>
                                            <span class='px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/50'>{$encumbered}</span>
                                        </div>
                                        <div class='leading-relaxed space-y-1'>
                                            <div class='text-gray-900 dark:text-gray-100'>🚗 <span class='text-gray-600 dark:text-gray-400 font-medium'>Make / Brand :</span> <strong class='text-gray-900 dark:text-white font-bold'>{$desc}</strong></div>
                                            <div class='pl-6 text-gray-900 dark:text-gray-100'><span class='text-gray-600 dark:text-gray-400 font-medium'>Year Model :</span> <strong class='text-gray-900 dark:text-gray-100 font-semibold'>{$year}</strong></div>
                                            <div class='pl-6 text-gray-900 dark:text-gray-100'><span class='text-gray-600 dark:text-gray-400 font-medium'>Engine # :</span> <strong class='font-mono text-gray-900 dark:text-gray-100 font-semibold'>{$engine}</strong></div>
                                            <div class='pl-6 text-gray-900 dark:text-gray-100'><span class='text-gray-600 dark:text-gray-400 font-medium'>Chassis # :</span> <strong class='font-mono text-gray-900 dark:text-gray-100 font-semibold'>{$chassis}</strong></div>
                                            <div class='pl-6 text-gray-900 dark:text-gray-100'><span class='text-gray-600 dark:text-gray-400 font-medium'>Plate # :</span> <strong class='font-mono text-gray-900 dark:text-gray-100 font-semibold'>{$plate}</strong></div>
                                            <div class='pl-6 text-gray-900 dark:text-gray-100'><span class='text-gray-600 dark:text-gray-400 font-medium'>File No. / MV File # :</span> <strong class='font-mono text-gray-900 dark:text-gray-100 font-semibold'>{$mvFile}</strong></div>
                                        </div>
                                    </div>
                                ";
                            }

                            foreach ($rems as $r) {
                                $tct = e($r->tct_oct_no ?: 'No Title No.');
                                $lot = e($r->lot_no ? "Lot {$r->lot_no}" : '—');
                                $blk = e($r->block_no ? "Blk {$r->block_no}" : '—');
                                $area = e($r->area_sqm ? "{$r->area_sqm} sqm" : '—');
                                $loc = e($r->location ?: 'Location not specified');
                                $owner = e($r->registered_owner ?: '—');
                                $remStatus = e($r->rem_status ?: 'Active');

                                $html .= "
                                    <div class='p-3.5 rounded-xl bg-gray-50 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-800 space-y-3 shadow-sm'>
                                        <div class='flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-gray-200/80 dark:border-gray-800'>
                                            <div class='flex items-center gap-2'>
                                                <span class='text-sm'>🏠</span>
                                                <span class='font-bold text-gray-900 dark:text-gray-100 font-mono text-xs sm:text-sm'>Title # {$tct}</span>
                                                <span class='text-[11px] text-gray-500 dark:text-gray-400'>• {$loc}</span>
                                            </div>
                                            <div>
                                                <span class='px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300'>{$remStatus}</span>
                                            </div>
                                        </div>

                                        <div class='grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 text-xs'>
                                            <div class='p-2 rounded-lg bg-white dark:bg-gray-800/60 border border-gray-200/70 dark:border-gray-700/60'>
                                                <span class='text-gray-400 dark:text-gray-500 block text-[10px] font-medium'>TCT / OCT No.</span>
                                                <span class='font-semibold font-mono text-gray-900 dark:text-gray-100'>{$tct}</span>
                                            </div>
                                            <div class='p-2 rounded-lg bg-white dark:bg-gray-800/60 border border-gray-200/70 dark:border-gray-700/60'>
                                                <span class='text-gray-400 dark:text-gray-500 block text-[10px] font-medium'>Lot / Blk / Area</span>
                                                <span class='font-semibold text-gray-900 dark:text-gray-100'>{$lot} / {$blk} ({$area})</span>
                                            </div>
                                            <div class='p-2 rounded-lg bg-white dark:bg-gray-800/60 border border-gray-200/70 dark:border-gray-700/60'>
                                                <span class='text-gray-400 dark:text-gray-500 block text-[10px] font-medium'>Registered Owner</span>
                                                <span class='font-semibold text-gray-900 dark:text-gray-100 break-words'>{$owner}</span>
                                            </div>
                                            <div class='p-2 rounded-lg bg-white dark:bg-gray-800/60 border border-gray-200/70 dark:border-gray-700/60'>
                                                <span class='text-gray-400 dark:text-gray-500 block text-[10px] font-medium'>Property Location</span>
                                                <span class='font-semibold text-gray-900 dark:text-gray-100 break-words'>{$loc}</span>
                                            </div>
                                        </div>
                                    </div>
                                ";
                            }
                            $html .= "</div>";

                            return new \Illuminate\Support\HtmlString($html);
                        })
                        ->columnSpanFull(),
                ]),

            // SECTION 2C: Workflow & Evaluation Routing
            'workflow_status_section' => Section::make('Workflow & Evaluation Status')
                ->description('Application approval routing, stage pipeline, and condition')
                ->icon('heroicon-o-arrow-path')
                ->schema([
                    Select::make('approval_status')
                        ->label('Approval Status')
                        ->options([
                            'Pending Approval' => '⏳ Pending Approval (For Evaluation)',
                            'Approved'         => '🟢 Approved (Loan Released)',
                            'Branch Updated'   => '🔔 Branch Updated (Awaiting HO Review)',
                            'Disapproved'      => '🔴 Disapproved (Deficiencies)',
                        ])
                        ->default('Pending Approval')
                        ->disabled(fn (string $operation) => $operation === 'create' || (
                            auth()->user()?->isBranchUser()
                            || auth()->user()?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist', 'Branch Manager', 'branch_manager'])
                            || in_array(auth()->user()?->role?->code, ['mortgage_document_specialist', 'branch_manager'])
                        ))
                        ->dehydrated()
                        ->required(),

                    Select::make('status')
                        ->label('Application Stage')
                        ->options(function ($get) {
                            $collateralType = $get('collateral_type');

                            $query = \App\Models\ApplicationStatus::query()
                                ->where('is_active', true)
                                ->orderBy('sort_order');

                            if (in_array($collateralType, ['cm_certification', 'rem_certification'])) {
                                $allowed = ['Initial Assessment', 'For Notary', 'Notarized', 'Fully Paid', 'Deceased'];
                                $query->whereIn('name', $allowed);
                            } elseif ($collateralType === 'cm_amendment') {
                                $allowed = ['Initial Assessment', 'For Notary', 'Notarized', 'For BIR', 'For Bir', 'BIR Paid', 'Bir Paid', 'For PPSR', 'PPSR Paid', 'Fully Paid', 'Deceased', 'Released For Self-Cancellation', 'For Cancellation', 'Mortgage Released'];
                                $query->whereIn('name', $allowed);
                            } elseif (in_array($collateralType, ['real_estate_mortgage', 'rem_amendment'])) {
                                $query->whereNotIn('name', ['For PPSR', 'For Ppsr', 'PPSR Paid', 'Ppsr Paid', 'For LTO', 'For Lto']);
                            } elseif ($collateralType === 'chattel_mortgage') {
                                $query->whereNotIn('name', ['For ROD', 'For Rod', 'ROD Paid', 'Rod Paid', 'ROD Released', 'Rod Released', "For Assessor's"]);
                            }

                            return $query->get()->mapWithKeys(fn ($item) => [$item->name => "{$item->sort_order}. {$item->name}"]);
                        })
                        ->live()
                        ->default('Initial Assessment')
                        ->required(),

                    Select::make('sub_status')
                        ->label('Stage Condition / Sub-Status')
                        ->options([
                            'none'                => '🟢 On Process (Standard)',
                            'internal_deficiency' => '🏢 Internal Deficiency',
                            'external_deficiency' => '🏛️ External Deficiency',
                        ])
                        ->default('none')
                        ->disabled(fn () => (
                            auth()->user()?->isBranchUser()
                            || auth()->user()?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist', 'Branch Manager', 'branch_manager'])
                            || in_array(auth()->user()?->role?->code, ['mortgage_document_specialist', 'branch_manager'])
                        ))
                        ->helperText(fn () => (
                            auth()->user()?->isBranchUser()
                            || auth()->user()?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist', 'Branch Manager', 'branch_manager'])
                            || in_array(auth()->user()?->role?->code, ['mortgage_document_specialist', 'branch_manager'])
                        ) ? '🔒 Stage condition / sub-status is strictly managed by Head Office Evaluators.' : null)
                        ->dehydrated()
                        ->live()
                        ->required(),

                    Textarea::make('deficiency_notes')
                        ->label('Deficiency Findings / Requirements')
                        ->placeholder('Specify missing requirements, discrepancy details, or instructions for compliance...')
                        ->rows(3)
                        ->autosize()
                        ->visible(fn ($get) => in_array($get('sub_status'), ['internal_deficiency', 'external_deficiency']))
                        ->columnSpanFull(),
                ]),

            // SECTION 2D: Evaluator Findings & Specific Remarks
            'evaluator_notes_section' => Section::make('Evaluator Findings & Remarks')
                ->description('Head Office evaluation findings, deficiency remarks, or specific instructions')
                ->icon('heroicon-o-pencil-square')
                ->schema([
                    Textarea::make('notes')
                        ->label('Application Notes / Remarks (Head Office Assessment)')
                        ->placeholder('Enter official assessment notes, specific findings, or instructions...')
                        ->autosize()
                        ->disabled(fn () => (
                            auth()->user()?->isBranchUser()
                            || auth()->user()?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist', 'Branch Manager', 'branch_manager'])
                            || in_array(auth()->user()?->role?->code, ['mortgage_document_specialist', 'branch_manager'])
                        ))
                        ->dehydrated()
                        ->helperText(fn () => (
                            auth()->user()?->isBranchUser()
                            || auth()->user()?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist', 'Branch Manager', 'branch_manager'])
                            || in_array(auth()->user()?->role?->code, ['mortgage_document_specialist', 'branch_manager'])
                        ) ? '🔒 Official assessment remarks are strictly managed by Head Office Evaluators.' : null)
                        ->columnSpanFull(),
                ]),

            // SECTION 2F: Comment / Conversation Thread
            'comments_section' => Section::make('Comment / Conversation Thread')
                ->description('Evaluation remarks, deficiency notes, and branch compliance thread')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->collapsible()
                ->collapsed()
                ->compact()
                ->visible(fn ($record) => filled($record?->id))
                ->schema([
                    Placeholder::make('notes_history_content')
                        ->hiddenLabel()
                        ->content(function ($record) {
                            if (! $record) {
                                return null;
                            }

                            $notes = $record->applicationNotes()->with(['user.role'])->oldest()->get();

                            return new \Illuminate\Support\HtmlString(
                                view('filament.components.comment-timeline', [
                                    'notes'  => $notes,
                                    'record' => $record,
                                ])->render()
                            );
                        })
                        ->columnSpanFull(),

                    Textarea::make('branch_comment')
                        ->label('Add Comment / Reply')
                        ->placeholder('Type your comment, compliance update, or response here...')
                        ->autosize()
                        ->dehydrated(false)
                        ->afterStateHydrated(fn (Textarea $component) => $component->state(''))
                        ->helperText('💬 Comments entered here will be added to the conversation thread above upon saving.')
                        ->columnSpanFull(),
                ]),

            // Full original Loan Application Details section for Create Wizard & standard forms
            'loan_details_section' => Section::make('Loan Application Details')
                ->description('Loan reference number, requested amount, branch, and status')
                ->icon('heroicon-o-banknotes')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('application_no')
                            ->label('Application ID')
                            ->placeholder('e.g. API-LA-2026-00008')
                            ->default(function () {
                                $year = date('Y');
                                $nextId = (\App\Models\LoanApplication::max('id') ?? 0) + 1;
                                return 'API-LA-' . $year . '-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
                            })
                            ->disabled()
                            ->dehydrated()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->suffixAction(
                                Action::make('copyAppId')
                                    ->icon('heroicon-m-clipboard-document')
                                    ->color('gray')
                                    ->tooltip('Copy Application ID')
                                    ->alpineClickHandler('window.navigator.clipboard.writeText($wire.get("data.application_no")); new FilamentNotification().title("Application ID copied to clipboard!").success().send();')
                            ),
                        Select::make('branch_office_id')
                            ->label('Processing OIC Branch')
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
                        TextInput::make('requested_amount')
                            ->label('Member Requested Loan Amount (₱)')
                            ->prefix('₱')
                            ->placeholder('0.00')
                            ->live(onBlur: true)
                            ->formatStateUsing(fn ($state) => filled($state) ? number_format((float) str_replace(',', '', (string) $state), 2, '.', ',') : null)
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? (float) str_replace(',', '', (string) $state) : null)
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (filled($state)) {
                                    $numeric = (float) str_replace(',', '', (string) $state);
                                    $set('requested_amount', number_format($numeric, 2, '.', ','));
                                }
                            })
                            ->required(),
                    ]),
                    Grid::make(3)->schema([
                        TextInput::make('loan_account_no')
                            ->label('Loan Account Number')
                            ->placeholder('e.g. LN-ACCT-2026-00123')
                            ->maxLength(255)
                            ->suffixAction(
                                Action::make('copyAccountNo')
                                    ->icon('heroicon-m-clipboard-document')
                                    ->color('gray')
                                    ->tooltip('Copy Account Number')
                                    ->alpineClickHandler('window.navigator.clipboard.writeText($wire.get("data.loan_account_no")); new FilamentNotification().title("Account Number copied to clipboard!").success().send();')
                            ),
                        DatePicker::make('date_granted')
                            ->label('Date Granted')
                            ->native(false)
                            ->displayFormat('F d, Y')
                            ->live()
                            ->maxDate(now()->addYear()),
                        DatePicker::make('maturity_date')
                            ->label('Maturity Date')
                            ->native(false)
                            ->displayFormat('F d, Y')
                            ->minDate(fn ($get) => $get('date_granted') ?: null)
                            ->maxDate(now()->addYears(15))
                            ->afterOrEqual('date_granted')
                            ->helperText('Must be on or after Date Granted.'),
                    ]),
                    Grid::make(3)->schema([
                        Select::make('collateral_type')
                            ->label('Mortgage Security Type')
                            ->placeholder('Select an option')
                            ->options([
                                'real_estate_mortgage' => 'Real Estate Mortgage (REM)',
                                'rem_certification' => 'Real Estate Mortgage Certification',
                                'rem_amendment' => 'Amendment Of Real Estate Mortgage',
                                'chattel_mortgage' => 'Chattel Mortgage (CM)',
                                'cm_certification' => 'Chattel Mortgage Certification',
                                'cm_amendment' => 'Amendment Of Chattel Mortgage (Requires Memorandum Approval)',
                            ])
                            ->disableOptionWhen(fn (string $value): bool => $value === 'cm_amendment')
                            ->native(false)
                            ->disabled(fn (string $operation, $record) => $operation === 'edit' && filled($record?->collateral_type))
                            ->dehydrated()
                            ->required()
                            ->live()
                            ->suffixAction(
                                Action::make('viewMortgageGuidelines')
                                    ->icon('heroicon-m-information-circle')
                                    ->color('primary')
                                    ->tooltip('View Documentary Guidelines & Requirements (Slide View)')
                                    ->modalHeading('Official Mortgage Documentary Guidelines (REM & CM Requirements)')
                                    ->modalDescription('Official guide on mandatory documentary requirements (23 REM Requirements & 25 CM Requirements) prior to acceptance and endorsement.')
                                    ->modalContent(fn ($get) => view('filament.modals.mortgage-guidelines-modal', [
                                        'collateralType' => $get('collateral_type'),
                                    ]))
                                    ->modalSubmitAction(false)
                                    ->modalCancelActionLabel('Close')
                                    ->slideOver()
                                    ->modalWidth('6xl')
                            ),
                        Select::make('approval_status')
                            ->label('Approval Status')
                            ->options([
                                'Pending Approval' => '⏳ Pending Approval (For Evaluation)',
                                'Approved'         => '🟢 Approved (Loan Released)',
                                'Branch Updated'   => '🔔 Branch Updated (Awaiting HO Review)',
                                'Disapproved'      => '🔴 Disapproved (Deficiencies)',
                            ])
                            ->default('Pending Approval')
                            ->disabled(fn (string $operation) => $operation === 'create' || (
                                auth()->user()?->isBranchUser()
                                || auth()->user()?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist', 'Branch Manager', 'branch_manager'])
                                || in_array(auth()->user()?->role?->code, ['mortgage_document_specialist', 'branch_manager'])
                            ))
                            ->dehydrated()
                            ->required(),
                        Select::make('status')
                            ->label('Application Stage')
                            ->options(function () {
                                return \App\Models\ApplicationStatus::query()
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->get()
                                    ->mapWithKeys(fn ($item) => [$item->name => "{$item->sort_order}. {$item->name}"]);
                            })
                            ->default('Initial Assessment')
                            ->required(),
                    ]),
                    Grid::make(1)->schema([
                        Select::make('sub_status')
                            ->label('Stage Condition / Sub-Status')
                            ->options([
                                'none'                => '🟢 On Process (Standard)',
                                'internal_deficiency' => '🏢 Internal Deficiency',
                                'external_deficiency' => '🏛️ External Deficiency',
                            ])
                            ->default('none')
                            ->disabled(fn () => (
                                auth()->user()?->isBranchUser()
                                || auth()->user()?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist', 'Branch Manager', 'branch_manager'])
                                || in_array(auth()->user()?->role?->code, ['mortgage_document_specialist', 'branch_manager'])
                            ))
                            ->helperText(fn () => (
                                auth()->user()?->isBranchUser()
                                || auth()->user()?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist', 'Branch Manager', 'branch_manager'])
                                || in_array(auth()->user()?->role?->code, ['mortgage_document_specialist', 'branch_manager'])
                            ) ? '🔒 Stage condition / sub-status is strictly managed by Head Office Evaluators.' : null)
                            ->dehydrated()
                            ->required(),
                    ]),
                    Textarea::make('notes')
                        ->label('Application Notes / Remarks (Head Office Assessment)')
                        ->placeholder('Enter official assessment notes, requirements, or evaluation findings...')
                        ->autosize()
                        ->disabled(fn () => (
                            auth()->user()?->isBranchUser()
                            || auth()->user()?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist', 'Branch Manager', 'branch_manager'])
                            || in_array(auth()->user()?->role?->code, ['mortgage_document_specialist', 'branch_manager'])
                        ))
                        ->helperText(fn () => (
                            auth()->user()?->isBranchUser()
                            || auth()->user()?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist', 'Branch Manager', 'branch_manager'])
                            || in_array(auth()->user()?->role?->code, ['mortgage_document_specialist', 'branch_manager'])
                        ) ? '🔒 Official assessment remarks are strictly managed by Head Office Evaluators.' : null)
                        ->columnSpanFull(),
                ]),

            // SECTION 3: Collateral Information
            'collateral_section' => Section::make('Collateral Information')
                ->description('Real Estate Mortgage or Chattel Mortgage collateral specifications')
                ->icon('heroicon-o-home')
                ->schema([
                    // Real Estate Mortgage Repeater
                    Repeater::make('realEstateCollaterals')
                        ->label('Title Information')
                        ->relationship('realEstateCollaterals')
                        ->visible(fn ($get) => in_array($get('collateral_type'), ['real_estate_mortgage', 'rem_certification', 'rem_amendment']))
                        ->schema([
                            Grid::make(3)->schema([
                                Select::make('title_details')
                                    ->label('Title Details')
                                    ->placeholder('Select an option')
                                    ->options([
                                        'OCT (Original Certificate of Title)' => 'OCT (Original Certificate of Title)',
                                        'TCT (Transfer Certificate of Title)' => 'TCT (Transfer Certificate of Title)',
                                        'CCT (Condominium Certificate of Title)' => 'CCT (Condominium Certificate of Title)',
                                    ])
                                    ->required(),
                                TextInput::make('title_number')
                                    ->label('Title Number')
                                    ->placeholder('e.g. TCT-123456')
                                    ->required(),
                                TextInput::make('title_owner_name')
                                    ->label('Registered Title Owner/s')
                                    ->placeholder('e.g. Juan Dela Cruz & Maria Dela Cruz')
                                    ->required(),
                            ]),
                            Grid::make(2)->schema([
                                TextInput::make('location')
                                    ->label('Property Location / Address')
                                    ->placeholder('e.g. Brgy. Cugman, Cagayan de Oro City')
                                    ->required(),
                                Select::make('rod_branch_id')
                                    ->label('ROD Branch Office')
                                    ->placeholder('Select an option')
                                    ->relationship('rodBranch', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ]),
                            Grid::make(3)->schema([
                                TextInput::make('tax_dec_land')
                                    ->label('Tax Declaration No. (Land)')
                                    ->placeholder('e.g. TDL-2026-001'),
                                TextInput::make('land_owner_name')
                                    ->label("Tax Declaration Owner's Name (Land)")
                                    ->placeholder('e.g. Juan Dela Cruz'),
                                TextInput::make('pin_land')
                                    ->label('Property Identification No. (Land)')
                                    ->placeholder('e.g. 162-01-0001-015-04'),
                            ]),
                            Repeater::make('building_tax_declarations')
                                ->label('Building Tax Declarations (Optional)')
                                ->schema([
                                    Grid::make(3)->schema([
                                        TextInput::make('tax_dec_bldg')
                                            ->label('Tax Dec # (Building)')
                                            ->placeholder('e.g. TDB-2026-001'),
                                        TextInput::make('bldg_owner_name')
                                            ->label("Tax Dec Owner's Name (Building)")
                                            ->placeholder('e.g. Juan Dela Cruz'),
                                        TextInput::make('pin_bldg')
                                            ->label('PIN Tax Dec # (Building)')
                                            ->placeholder('e.g. 162-01-0001-015-05'),
                                    ]),
                                ])
                                ->defaultItems(0)
                                ->addActionLabel('Add Building Tax Declaration')
                                ->columns(1),
                            Grid::make(1)->schema([
                                Select::make('rem_status')
                                    ->label('Title Status')
                                    ->options([
                                        'New' => '🟢 New',
                                        'OIC Encumbered' => '🟡 OIC Encumbered',
                                        'REL/E' => '🔵 REL/E',
                                    ])
                                    ->default('New')
                                    ->required(),
                            ]),
                        ])
                        ->addActionLabel('Add Another Title Info')
                        ->columns(1),

                    // Chattel Mortgage Repeater
                    Repeater::make('chattelCollaterals')
                        ->label('Vehicle Information')
                        ->relationship('chattelCollaterals')
                        ->visible(fn ($get) => in_array($get('collateral_type'), ['chattel_mortgage', 'cm_certification', 'cm_amendment']))



                        ->schema([
                            Grid::make(3)->schema([
                                TextInput::make('owner_name')
                                    ->label("Complete Owner's Name")
                                    ->placeholder('e.g. Juan Dela Cruz')
                                    ->required(),
                                TextInput::make('mv_file_no')
                                    ->label('File No. / MV File No.')
                                    ->placeholder('e.g. MV-12345678'),
                                TextInput::make('plate_no')
                                    ->label('Plate No.')
                                    ->placeholder('e.g. ABC-1234')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if (blank($state)) return;
                                        $monthVal = \App\Models\ChattelCollateral::computeRenewalMonthFromPlate($state);
                                        if ($monthVal) {
                                            $set('registration_renewal_date', $monthVal);

                                            $c = \Carbon\Carbon::parse($monthVal . '-01')->startOfMonth();
                                            if ($c->isPast()) {
                                                $set('cm_status', 'Expired');
                                            } elseif ($c->diffInDays(now()) <= 30) {
                                                $set('cm_status', 'Due for Renewal');
                                            } else {
                                                $set('cm_status', 'Active');
                                            }
                                        }
                                    }),
                            ]),
                            Grid::make(3)->schema([
                                TextInput::make('engine_no')
                                    ->label('Engine No.')
                                    ->placeholder('e.g. ENG-4D56-1234'),
                                TextInput::make('chassis_no')
                                    ->label('Chassis No.')
                                    ->placeholder('e.g. CHS-KBD41-5678'),
                                TextInput::make('year_model')
                                    ->label('Year Model')
                                    ->placeholder('e.g. 2023')
                                    ->numeric()
                                    ->length(4)
                                    ->minValue(1950)
                                    ->maxValue(now()->year + 2)
                                    ->extraInputAttributes(['maxlength' => 4])
                                    ->helperText('4-digit year only (e.g. 2023)')
                                    ->required(),
                            ]),
                            Grid::make(3)->schema([
                                Select::make('encumbered_to')
                                    ->label('Encumbered Status')
                                    ->options([
                                        'NEW'            => '🟢 NEW',
                                        'OIC ENCUMBERED' => '🟡 OIC ENCUMBERED',
                                        'REL/E'          => '🔵 REL/E',
                                    ])
                                    ->default('NEW')
                                    ->required(),
                                TextInput::make('registration_renewal_date')
                                    ->label('Registration Renewal Date')
                                    ->type('month')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if (blank($state)) return;
                                        try {
                                            $c = \Carbon\Carbon::parse($state)->startOfMonth();
                                            if ($c->isPast()) {
                                                $set('cm_status', 'Expired');
                                            } elseif ($c->diffInDays(now()) <= 30) {
                                                $set('cm_status', 'Due for Renewal');
                                            } else {
                                                $set('cm_status', 'Active');
                                            }
                                        } catch (\Throwable $e) {}
                                    })
                                    ->formatStateUsing(function ($state) {
                                        if (blank($state)) return null;
                                        try {
                                            return \Carbon\Carbon::parse($state)->format('Y-m');
                                        } catch (\Throwable $e) {
                                            return null;
                                        }
                                    })
                                    ->dehydrateStateUsing(function ($state) {
                                        if (blank($state)) return null;
                                        try {
                                            return \Carbon\Carbon::parse($state)->startOfMonth()->format('Y-m-d');
                                        } catch (\Throwable $e) {
                                            return null;
                                        }
                                    })
                                    ->helperText(function (callable $get) {
                                        $plate = $get('plate_no');
                                        $desc = \App\Models\ChattelCollateral::getLtoScheduleDescription($plate);
                                        return $desc
                                            ? "⚡ Auto: {$desc} (Maaaring i-adjust manwal)"
                                            : 'Month & Year only';
                                    }),
                                Select::make('cm_status')
                                    ->label('Vehicle Status')
                                    ->options([
                                        'Active' => '🟢 Active',
                                        'Due for Renewal' => '🟡 Due for Renewal',
                                        'Expired' => '🔴 Expired',
                                    ])
                                    ->default('Active')
                                    ->required(),
                            ]),
                            TextInput::make('vehicle_description')
                                ->label('Vehicle Description (Make/Brand, Series, Body Type)')
                                ->placeholder('e.g. Toyota Hilux 2.4L Pickup Truck')
                                ->columnSpanFull(),
                        ])
                        ->addActionLabel('Add Another Vehicle Info')
                        ->columns(1),
                ]),

            // SECTION 4: Documentation Uploads
            'document_section' => Section::make('Documentation Requirements Uploads')
                ->description('Uploaded scanned documentation files (.pdf, .jpg, .jpeg, .png only)')
                ->icon('heroicon-o-document-arrow-up')
                ->collapsible()
                ->headerActions([
                    Action::make('openDocumentSlideover')
                        ->label('Interactive Slide-Over Viewer')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->slideOver()
                        ->modalWidth('7xl')
                        ->modalHeading(fn ($record): string => "Scanned Documents — " . ($record?->application_no ?? 'Loan Documents') . ($record?->member ? " ({$record->member->full_name})" : ''))
                        ->modalDescription('Interactive slide-over document viewer with zoom, 90° rotation, print, and downloads')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close Viewer')
                        ->modalContent(fn ($record) => view('filament.modals.document-slideover-viewer', [
                            'record' => $record,
                        ]))
                        ->visible(fn ($record) => $record && $record->getMedia('*')->isNotEmpty()),
                    static::getQuickUploadTableAction()
                        ->visible(fn ($record) => (bool) $record),
                ])
                ->schema([
                    \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('spatie_documents')
                        ->label('Upload Document Files (PDF, JPG, PNG, WebP)')
                        ->collection('loan_application_documents')
                        ->disk('public')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/webp'])
                        ->imageResizeMode('contain')
                        ->imageResizeTargetWidth('1200')
                        ->imageResizeTargetHeight('1200')
                        ->imageResizeUpscale(false)
                        ->helperText('Strictly allowed file formats: .pdf, .jpg, .jpeg, .png, .webp (Images auto-resized to 1200px max for fast saving)')
                        ->preserveFilenames()
                        ->multiple()
                        ->reorderable()
                        ->openable()
                        ->downloadable()
                        ->panelLayout('grid')
                        ->imagePreviewHeight('180px')
                        ->columnSpanFull()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->minFiles(fn (string $operation): ?int => $operation === 'create' ? 1 : null)
                        ->validationMessages([
                            'required' => 'Please upload at least one scanned document (.pdf, .jpg, .jpeg, .png, or .webp).',
                            'minFiles' => 'Please upload at least one scanned document (.pdf, .jpg, .jpeg, .png, or .webp).',
                        ]),
                ]),

            // SECTION 5: Document Processing Tracker
            'document_tracking_section' => Section::make('📄 Document Processing Tracker')
                ->description('Track document movement through each government agency — Notary, BIR, ROD, LTO, Assessor\'s, and Encumbrance registration')
                ->icon('heroicon-o-clipboard-document-check')
                ->collapsible()
                ->schema([

                    // ── NOTARY ──────────────────────────────────────────────
                    Section::make('Notary')
                        ->icon('heroicon-o-pencil-square')
                        ->compact()
                        ->collapsible()
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('notary_name')
                                    ->label('Notary Public / Notarial Office')
                                    ->placeholder('e.g. Atty. Juan Dela Cruz Notarial Office'),
                                DatePicker::make('notarized_date')
                                    ->label('Date Notarized'),
                            ]),
                            Grid::make(['default' => 2, 'md' => 4])->schema([
                                TextInput::make('notary_doc_no')
                                    ->label('Doc. No.')
                                    ->placeholder('e.g. 142'),
                                TextInput::make('notary_page_no')
                                    ->label('Page No.')
                                    ->placeholder('e.g. 29'),
                                TextInput::make('notary_book_no')
                                    ->label('Book No.')
                                    ->placeholder('e.g. XII or 15'),
                                TextInput::make('notary_series_no')
                                    ->label('Series of')
                                    ->placeholder('e.g. ' . now()->year)
                                    ->default(fn () => (string) now()->year),
                            ]),
                        ]),

                    // ── BIR ─────────────────────────────────────────────────
                    Section::make('Bureau of Internal Revenue (BIR)')
                        ->icon('heroicon-o-building-office')
                        ->compact()
                        ->collapsible()
                        ->visible(fn ($get) => in_array($get('collateral_type'), ['real_estate_mortgage', 'rem_amendment', 'chattel_mortgage', 'cm_amendment']))
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextInput::make('bir_reference_no')
                                    ->label('eFPS Reference Number')
                                    ->placeholder('e.g. 652600074997564'),
                                TextInput::make('bir_amount_paid')
                                    ->label('DST Amount Paid (₱)')
                                    ->prefix('₱')
                                    ->placeholder('0.00')
                                    ->numeric(),
                            ]),
                        ]),

                    // ── ROD (REM only) ──────────────────────────────────────
                    Section::make('Registry of Deeds (ROD)')
                        ->icon('heroicon-o-building-library')
                        ->compact()
                        ->collapsible()
                        ->visible(fn ($get) => in_array($get('collateral_type'), ['real_estate_mortgage', 'rem_amendment']))
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                Select::make('current_rod_status_id')
                                    ->label('ROD Processing Status')
                                    ->relationship('currentRodStatus', 'name', fn ($query) => $query->orderBy('step_number'))
                                    ->placeholder('Select ROD Status')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Current stage in ROD / LRA LOTS workflow'),
                                TextInput::make('rod_entry_no')
                                    ->label('Ref No. / EPEB Entry #:')
                                    ->placeholder('e.g. EPEB496/2026001432 or 1038896086'),
                                DatePicker::make('rod_payment_date')
                                    ->label('Date of Payment / Registration:'),
                                TextInput::make('rod_amount_paid')
                                    ->label('ROD Amount Paid (₱):')
                                    ->prefix('₱')
                                    ->placeholder('0.00')
                                    ->numeric(),
                            ]),
                        ]),

                    // ── PPSR (CM only) ───────────────────────────────────────
                    Section::make('Personal Property Security Registry (PPSR)')
                        ->icon('heroicon-o-shield-check')
                        ->compact()
                        ->collapsible()
                        ->visible(fn ($get) => in_array($get('collateral_type'), ['chattel_mortgage', 'cm_amendment']))
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextInput::make('ppsr_notice_no')
                                    ->label('Notice Registration #:')
                                    ->placeholder('e.g. PPSR-260724001343'),
                                DatePicker::make('ppsr_initial_notice_date')
                                    ->label('Initial Notice Registration Date'),
                                DatePicker::make('ppsr_ctc_notice_date')
                                    ->label('Certified True Copy of Notice Registration Date'),
                                DatePicker::make('ppsr_payment_date')
                                    ->label('Date of Payment / Registration:'),
                            ]),
                        ]),

                    // ── LTO (CM only) ────────────────────────────────────────
                    Section::make('Land Transportation Office (LTO)')
                        ->icon('heroicon-o-truck')
                        ->compact()
                        ->collapsible()
                        ->visible(fn ($get) => $get('collateral_type') === 'chattel_mortgage')
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextInput::make('lto_office')
                                    ->label('LTO Office')
                                    ->placeholder('e.g. LTO CDO Extension Office'),
                                TextInput::make('lto_or_no')
                                    ->label('Official Receipt No. (OR No.)')
                                    ->placeholder('e.g. OR-2026-009876'),
                                DatePicker::make('lto_payment_date')
                                    ->label('Date Paid / Registered'),
                                TextInput::make('lto_amount_paid')
                                    ->label('Amount Paid (₱)')
                                    ->prefix('₱')
                                    ->placeholder('0.00')
                                    ->numeric(),
                                DatePicker::make('lto_release_date')
                                    ->label('Date Released / Returned to Branch')
                                    ->columnSpan(['default' => 1, 'md' => 2]),
                            ]),
                        ]),

                    // ── ASSESSOR'S OFFICE (REM only) ─────────────────────────
                    Section::make("Assessor's Office")
                        ->icon('heroicon-o-map')
                        ->compact()
                        ->collapsible()
                        ->visible(fn ($get) => in_array($get('collateral_type'), ['real_estate_mortgage', 'rem_amendment']))
                        ->schema([
                            Grid::make(3)->schema([
                                TextInput::make('assessors_office')
                                    ->label("Assessor's Office")
                                    ->placeholder('e.g. City Assessor\'s Office — CDO')
                                    ->columnSpan(1),
                                DatePicker::make('assessors_received_date')
                                    ->label('Date Received / Filed'),
                                DatePicker::make('assessors_release_date')
                                    ->label('Date Released / Returned'),
                            ]),
                        ]),

                    // ── ENCUMBRANCE, VAULT & MORTGAGE RELEASE ────────────────
                    Section::make('🔒 Encumbrance, Vault Custody & Mortgage Release')
                        ->description('Collateral annotation, physical vault custody location, and discharge of mortgage upon full payment')
                        ->icon('heroicon-o-lock-closed')
                        ->compact()
                        ->collapsible()
                        ->visible(fn ($get) => in_array($get('collateral_type'), ['real_estate_mortgage', 'chattel_mortgage', 'rem_amendment', 'cm_amendment']))
                        ->schema([
                            // Active Loan: Encumbrance & Custody
                            Section::make('Active Loan: Encumbrance & Physical Custody')
                                ->icon('heroicon-o-key')
                                ->compact()
                                ->schema([
                                    Grid::make(['default' => 1, 'md' => 3])->schema([
                                        DatePicker::make('encumbered_date')
                                            ->label('Date Encumbered / Annotated')
                                            ->helperText('Date annotation recorded on Title/CR'),
                                        TextInput::make('annotation_no')
                                            ->label('Annotation / Entry No.')
                                            ->placeholder('e.g. 2026-ANN-00123'),
                                        TextInput::make('vault_location')
                                            ->label('Vault / Custody Location')
                                            ->placeholder('e.g. HO Vault Box #4 / Branch Safe A')
                                            ->helperText('Physical envelope/box location in vault'),
                                    ]),
                                ]),

                            // Completed Loan: Mortgage Cancellation & Title Return
                            Section::make('Fully Paid: Cancellation of Mortgage & Title Return')
                                ->description('Collateral discharge handling: Member self-cancellation at ROD/LTO or office-assisted cancellation')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->compact()
                                ->schema([
                                    Grid::make(['default' => 1, 'md' => 3])->schema([
                                        Select::make('mortgage_release_status')
                                            ->label('Mortgage Release Status')
                                            ->options([
                                                'active'                     => '🔒 Active / Encumbered (In Custody)',
                                                'released_self_cancellation' => '👤 Released to Member (Self-Cancellation sa ROD/LTO)',
                                                'for_cancellation'           => '⏳ For Cancellation / Office In-Progress (Gisalig sa Opisina)',
                                                'cancelled_released'         => '✅ Cancelled & Title Returned to Member',
                                            ])
                                            ->default('active')
                                            ->native(false)
                                            ->live(),
                                        Radio::make('cancellation_mode')
                                            ->label('Cancellation Handling / Processing Mode')
                                            ->options([
                                                'member' => '👤 Member-Handled (Si Member ang maglalakad sa ROD/LTO)',
                                                'coop'   => '🏢 Office-Assisted (Gisalig sa Opisina / Tayo ang maglalakad)',
                                            ])
                                            ->default('member')
                                            ->afterStateHydrated(function ($component, $state) {
                                                if (blank($state)) {
                                                    $component->state('member');
                                                }
                                            })
                                            ->live()
                                            ->columnSpan(['default' => 1, 'md' => 2]),
                                    ]),

                                    Grid::make(['default' => 1, 'md' => 2])->schema([
                                        TextInput::make('clearance_no')
                                            ->label('Full Payment Clearance / Cert #')
                                            ->placeholder('e.g. CFP-2026-00123')
                                            ->helperText('Accounting clearance proving zero-balance'),
                                        TextInput::make('title_release_remarks')
                                            ->label('Release Remarks / Acknowledgment Slip #')
                                            ->placeholder('e.g. AR #88219, Owner\'s duplicate copy received in good order'),
                                    ]),

                                    // ── If Member-Handled (Self-Processing) ──
                                    Section::make('👤 Documents Issued to Member for Self-Cancellation (Turn-Over Checklist)')
                                        ->description('Check off the original documents handed over to the member or authorized representative')
                                        ->compact()
                                        ->visible(fn ($get) => ($get('cancellation_mode') ?? 'member') === 'member')
                                        ->schema([
                                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                                Checkbox::make('is_title_returned')
                                                    ->label('Owner\'s Duplicate Title (TCT/OCT/CCT) or Original LTO OR/CR returned'),
                                                Checkbox::make('is_cancellation_deed_returned')
                                                    ->label('Duly Notarized Deed of Release / Cancellation of Mortgage'),
                                                Checkbox::make('is_sec_cert_returned')
                                                    ->label('Secretary\'s Certificate / Board Resolution + Signatory IDs'),
                                                Checkbox::make('is_promissory_note_returned')
                                                    ->label('Original Promissory Note (Marked CANCELLED / PAID)'),
                                            ]),
                                            Grid::make(['default' => 1, 'md' => 4])->schema([
                                                DatePicker::make('title_released_date')
                                                    ->label('Date Documents Released')
                                                    ->helperText('Date handed to member'),
                                                Select::make('claimant_type')
                                                    ->label('Receiver Type')
                                                    ->options([
                                                        'borrower'       => '👤 Borrower Himself/Herself',
                                                        'authorized_rep' => '👥 Authorized Representative (with SPA)',
                                                    ])
                                                    ->default('borrower')
                                                    ->live(),
                                                TextInput::make('title_released_to')
                                                    ->label('Receiver Full Name')
                                                    ->placeholder('e.g. Juan Dela Cruz'),
                                                TextInput::make('rep_id_details')
                                                    ->label('Valid ID Presented')
                                                    ->placeholder('e.g. PhilSys ID #1234-5678-9012'),
                                            ]),
                                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                                TextInput::make('rep_spa_details')
                                                    ->label('SPA Details (if Representative)')
                                                    ->placeholder('e.g. Notarized SPA dated Oct 01, 2026 by Atty. Santos')
                                                    ->visible(fn ($get) => $get('claimant_type') === 'authorized_rep'),
                                                TextInput::make('title_released_by')
                                                    ->label('Released By (Custodian Staff)')
                                                    ->placeholder('e.g. Maria Santos (Vault Custodian)'),
                                            ]),
                                        ]),

                                    // ── If Office-Assisted (Coop Processing) ──
                                    Section::make('🏢 Office ROD / LTO Cancellation Processing (Gisalig sa Opisina)')
                                        ->description('Recorded when the cooperative/company processes the cancellation at ROD / LTO')
                                        ->compact()
                                        ->visible(fn ($get) => $get('cancellation_mode') === 'coop')
                                        ->schema([
                                            Grid::make(['default' => 1, 'md' => 4])->schema([
                                                DatePicker::make('cancellation_date')
                                                    ->label('Date Cancellation Filed'),
                                                TextInput::make('cancellation_entry_no')
                                                    ->label('Cancellation Entry / EPEB #')
                                                    ->placeholder('e.g. 2026-CAN-00045'),
                                                TextInput::make('cancellation_or_no')
                                                    ->label('ROD Cancellation OR #')
                                                    ->placeholder('e.g. OR-ROD-88912'),
                                                TextInput::make('cancellation_fee')
                                                    ->label('Cancellation Fee (₱)')
                                                    ->prefix('₱')
                                                    ->numeric()
                                                    ->placeholder('0.00'),
                                            ]),
                                            Grid::make(['default' => 1, 'md' => 3])->schema([
                                                DatePicker::make('title_released_date')
                                                    ->label('Date Title Returned to Member'),
                                                TextInput::make('title_released_to')
                                                    ->label('Received By (Member Name)')
                                                    ->placeholder('e.g. Juan Dela Cruz'),
                                                TextInput::make('title_released_by')
                                                    ->label('Released By (Custodian Staff)')
                                                    ->placeholder('e.g. Maria Santos (Vault Custodian)'),
                                            ]),
                                        ]),
                                ]),
                        ]),
                ]),

            // SECTION 6: Audit History & Activity Timeline
            'audit_timeline_section' => Section::make('🕒 Audit Trail & Activity Timeline')
                ->description('Chronological record of status changes, approvals, field edits, and user actions')
                ->icon('heroicon-o-clock')
                ->collapsible()
                ->collapsed()
                ->visible(fn ($record) => (bool) $record)
                ->schema([
                    ViewField::make('activity_timeline')
                        ->view('filament.components.loan-activity-timeline')
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function getCreateWizardSteps(): array
    {
        $sections = static::getFormSections();

        return [
            Step::make('Members Info')
                ->icon('heroicon-o-user')
                ->description('Select borrower member')
                ->schema([$sections['member_section']]),

            Step::make('Loan Application')
                ->icon('heroicon-o-banknotes')
                ->description('Loan details & charges')
                ->schema([$sections['loan_details_section']]),

            Step::make('Collateral Information')
                ->icon('heroicon-o-home')
                ->description('Real estate / chattel info')
                ->schema([$sections['collateral_section']]),

            Step::make('Scan / Upload')
                ->icon('heroicon-o-document-arrow-up')
                ->description('Upload required documents')
                ->schema([$sections['document_section']]),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        $sections = static::getFormSections();

        return $schema
            ->components([
                Tabs::make('Loan Application Details')
                    ->tabs([
                        Tab::make('Member & Loan Application')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Grid::make(['default' => 1, 'xl' => 12])
                                    ->schema([
                                        // LEFT COLUMN (7 of 12 columns): Borrower Profile, Loan Financials & Terms, Collateral Snapshot
                                        Grid::make(1)
                                            ->columnSpan(['default' => 12, 'xl' => 7])
                                            ->schema([
                                                $sections['member_section'],
                                                $sections['financials_section'],
                                                $sections['collateral_summary_section'],
                                            ]),

                                        // RIGHT COLUMN (5 of 12 columns): Workflow & Status, Evaluator Remarks, Comments
                                        Grid::make(1)
                                            ->columnSpan(['default' => 12, 'xl' => 5])
                                            ->schema([
                                                $sections['workflow_status_section'],
                                                $sections['evaluator_notes_section'],
                                                $sections['comments_section'],
                                            ]),
                                    ]),
                            ]),

                        Tab::make('Collateral Information')
                            ->icon('heroicon-o-home')
                            ->schema([$sections['collateral_section']]),

                        Tab::make('Document Processing Tracker')
                            ->icon('heroicon-o-clipboard-document-check')
                            ->schema([$sections['document_tracking_section']]),

                        Tab::make('Scan / Upload')
                            ->icon('heroicon-o-document-arrow-up')
                            ->schema([$sections['document_section']]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function getApproveTableAction(): Action
    {
        return Action::make('quick_approve')
            ->label('Approve Loan')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (LoanApplication $record) => ! in_array($record->approval_status, ['Approved', 'Branch Updated']) && (! auth()->user()?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist']) && auth()->user()?->role?->code !== 'mortgage_document_specialist'))
            ->modalHeading(fn (LoanApplication $record) => "Approve Loan: {$record->application_no}")
            ->modalDescription('Confirm loan approval and set the granted loan account details.')
            ->modalSubmitActionLabel('Confirm Approval')
            ->modalIcon('heroicon-o-check-circle')
            ->modalIconColor('success')
            ->form([
                Grid::make(3)->schema([
                    TextInput::make('loan_account_no')
                        ->label('Loan Account Number')
                        ->placeholder('e.g. LN-ACCT-2026-00123')
                        ->default(fn (LoanApplication $record) => $record->loan_account_no)
                        ->required()
                        ->maxLength(255),
                    DatePicker::make('date_granted')
                        ->label('Date Granted')
                        ->native(false)
                        ->displayFormat('F d, Y')
                        ->default(fn (LoanApplication $record) => $record->date_granted ?? now())
                        ->live()
                        ->maxDate(now()->addYear())
                        ->required(),
                    DatePicker::make('maturity_date')
                        ->label('Maturity Date')
                        ->native(false)
                        ->displayFormat('F d, Y')
                        ->default(fn (LoanApplication $record) => $record->maturity_date)
                        ->minDate(fn ($get) => $get('date_granted') ?: null)
                        ->maxDate(now()->addYears(15))
                        ->afterOrEqual('date_granted'),
                ]),
                Textarea::make('approval_notes')
                    ->label('Comment / Approval Reason (Optional)')
                    ->placeholder('Enter any evaluation comments or approval reasons...')
                    ->rows(5),
            ])
            ->action(function (LoanApplication $record, array $data, $livewire): void {
                $record->approval_status = 'Approved';
                $record->loan_account_no = $data['loan_account_no'] ?? $record->loan_account_no;
                $record->date_granted = $data['date_granted'] ?? $record->date_granted;
                $record->maturity_date = $data['maturity_date'] ?? $record->maturity_date;
                $record->save();

                // Send DB bell notification to current user + loan creator
                HandlesApprovalWorkflow::sendApprovalNotification($record, auth()->user());

                if (filled($data['approval_notes'] ?? null)) {
                    $record->applicationNotes()->create([
                        'user_id' => auth()->id() ?? $record->created_by_user_id,
                        'stage'   => ($record->status ?? 'Initial Assessment') . ' (Approval Comment)',
                        'note'    => $data['approval_notes'],
                    ]);
                }

                Notification::make()
                    ->title('✅ Loan Application Approved')
                    ->body("Application {$record->application_no} for " . ($record->member?->full_name ?? 'Borrower') . " has been APPROVED.")
                    ->success()
                    ->send();

                $livewire->redirect(request()->header('Referer') ?: url()->current());
            });
    }

    public static function getDisapproveTableAction(): Action
    {
        return Action::make('quick_disapprove')
            ->label('Disapprove Loan')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (LoanApplication $record) => ! in_array($record->approval_status, ['Disapproved', 'Branch Updated']) && (! auth()->user()?->hasRole(['Mortgage Document Specialist', 'mortgage_document_specialist']) && auth()->user()?->role?->code !== 'mortgage_document_specialist'))
            ->modalHeading(fn (LoanApplication $record) => "Disapprove Loan: {$record->application_no}")
            ->modalDescription('Specify the deficiency type and reason for disapproving this loan application.')
            ->modalSubmitActionLabel('Confirm Disapproval')
            ->modalIcon('heroicon-o-x-circle')
            ->modalIconColor('danger')
            ->form([
                Radio::make('deficiency_type')
                    ->label('Deficiency Type')
                    ->options([
                        'internal_deficiency' => '🏢 Internal Deficiency — Missing/incomplete documents from the branch or internal process',
                        'external_deficiency' => '🏛️ External Deficiency — Issues with external agencies (BIR, ROD, LTO, Assessor, etc.)',
                    ])
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('disapproval_reason')
                    ->label('Comment / Disapproval Reason')
                    ->placeholder('Specify the required corrections, missing documents, or disapproval reason...')
                    ->required()
                    ->rows(5),
            ])
            ->action(function (LoanApplication $record, array $data, $livewire): void {
                $record->approval_status = 'Disapproved';
                $record->sub_status = $data['deficiency_type'] ?? 'none';
                $record->save();

                // Send DB bell notification to current user + loan creator
                HandlesApprovalWorkflow::sendApprovalNotification($record, auth()->user());

                if (filled($data['disapproval_reason'] ?? null)) {
                    $record->applicationNotes()->create([
                        'user_id' => auth()->id() ?? $record->created_by_user_id,
                        'stage'   => ($record->status ?? 'Initial Assessment') . ' (Disapproved Comment)',
                        'note'    => $data['disapproval_reason'],
                    ]);
                }

                Notification::make()
                    ->title('❌ Loan Application Disapproved')
                    ->body("Application {$record->application_no} has been marked as DISAPPROVED.")
                    ->danger()
                    ->send();

                $livewire->redirect(request()->header('Referer') ?: url()->current());
            });
    }

    public static function getResubmitTableAction(): Action
    {
        return Action::make('resubmit_for_approval')
            ->label('Resubmit for Evaluation')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn (LoanApplication $record) => $record->approval_status === 'Disapproved')
            ->modalHeading(fn (LoanApplication $record) => "Resubmit Loan: {$record->application_no}")
            ->modalDescription('Submit this loan application back to Head Office for re-evaluation along with compliance or deficiency remarks.')
            ->modalSubmitActionLabel('Confirm & Resubmit')
            ->modalIcon('heroicon-o-arrow-path')
            ->modalIconColor('warning')
            ->form([
                Textarea::make('resubmission_notes')
                    ->label('Comment / Resubmission Reason')
                    ->placeholder('Specify the corrections made (e.g. Uploaded corrected PSA certificate, updated CTC of Title)...')
                    ->required()
                    ->rows(5),
            ])
            ->action(function (LoanApplication $record, array $data, $livewire): void {
                $record->approval_status = 'Pending Approval';
                $record->sub_status = 'none';
                $record->deficiency_notes = null;
                $record->save();

                // Send DB bell notification to current user + loan creator
                HandlesApprovalWorkflow::sendApprovalNotification($record, auth()->user());

                if (filled($data['resubmission_notes'] ?? null)) {
                    $record->applicationNotes()->create([
                        'user_id' => auth()->id() ?? $record->created_by_user_id,
                        'stage'   => ($record->status ?? 'Initial Assessment') . ' (Branch Resubmission Comment)',
                        'note'    => $data['resubmission_notes'],
                    ]);
                }

                Notification::make()
                    ->title('⏳ Loan Resubmitted for Evaluation')
                    ->body("Application {$record->application_no} for " . ($record->member?->full_name ?? 'Borrower') . " has been returned to the For Approval queue.")
                    ->warning()
                    ->send();

                $livewire->redirect(request()->header('Referer') ?: url()->current());
            });
    }

    public static function getDownloadArchiveTableAction(): Action
    {
        return Action::make('download_archive')
            ->label('Download')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('📥 Download Encrypted Documents Archive')
            ->modalIcon('heroicon-o-lock-closed')
            ->modalIconColor('success')
            ->modalDescription(function (LoanApplication $record) {
                $memberNo = $record->member?->member_no ?? 'MEMBER-GENERAL';
                $borrowerName = $record->member?->full_name ?? 'Borrower';
                $appNo = $record->application_no ?? 'LN-GENERAL';
                $cleanMemberNo = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $memberNo);
                $cleanName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '', (string) $borrowerName);
                $cleanName = preg_replace('/,{2,}/', ',', trim($cleanName));
                $expectedFileName = "{$cleanMemberNo}_{$cleanName}.zip";

                return new \Illuminate\Support\HtmlString("
                    <style>
                        .pmis-modal-card {
                            background: #ffffff;
                            border: 1px solid #e2e8f0;
                            color: #1e293b;
                            border-radius: 10px;
                            padding: 16px;
                            font-size: 13px;
                            text-align: left;
                        }
                        .dark .pmis-modal-card {
                            background: #18181b;
                            border: 1px solid #3f3f46;
                            color: #f4f4f5;
                        }
                        .pmis-row {
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                            gap: 12px;
                            padding: 7px 0;
                            border-bottom: 1px solid #f1f5f9;
                        }
                        .dark .pmis-row {
                            border-bottom-color: #27272a;
                        }
                        .pmis-label {
                            color: #64748b;
                            font-size: 12px;
                            font-weight: 600;
                        }
                        .dark .pmis-label {
                            color: #a1a1aa;
                        }
                        .pmis-val {
                            color: #0f172a;
                            font-weight: 700;
                            font-size: 12.5px;
                        }
                        .dark .pmis-val {
                            color: #ffffff;
                        }
                        .pmis-file-badge {
                            background: #e0f2fe;
                            color: #0369a1;
                            padding: 3px 8px;
                            border-radius: 6px;
                            font-family: monospace;
                            font-size: 12px;
                            font-weight: 700;
                        }
                        .dark .pmis-file-badge {
                            background: #082f49;
                            color: #38bdf8;
                            border: 1px solid #0369a1;
                        }
                        .pmis-pwd-box {
                            margin-top: 12px;
                            padding: 12px 14px;
                            background: #ecfdf5;
                            border: 1px solid #a7f3d0;
                            border-radius: 8px;
                        }
                        .dark .pmis-pwd-box {
                            background: #022c22;
                            border: 1px solid #059669;
                        }
                        .pmis-pwd-heading {
                            font-size: 11px;
                            font-weight: 700;
                            text-transform: uppercase;
                            letter-spacing: 0.05em;
                            color: #047857;
                        }
                        .dark .pmis-pwd-heading {
                            color: #34d399;
                        }
                        .pmis-pwd-code {
                            font-size: 18px;
                            font-weight: 800;
                            color: #065f46;
                            font-family: monospace;
                            letter-spacing: 0.5px;
                            margin-top: 4px;
                            user-select: all;
                            cursor: pointer;
                        }
                        .dark .pmis-pwd-code {
                            color: #6ee7b7;
                        }
                        .pmis-hint {
                            margin-top: 12px;
                            font-size: 11.5px;
                            line-height: 1.5;
                            color: #64748b;
                        }
                        .dark .pmis-hint {
                            color: #a1a1aa;
                        }
                    </style>
                    <div class='pmis-modal-card'>
                        <div class='pmis-row' style='padding-top: 0;'>
                            <span class='pmis-label'>📄 File Name:</span>
                            <span class='pmis-file-badge'>" . e($expectedFileName) . "</span>
                        </div>
                        <div class='pmis-row'>
                            <span class='pmis-label'>👤 Borrower:</span>
                            <span class='pmis-val'>" . e($borrowerName) . "</span>
                        </div>
                        <div class='pmis-row' style='border-bottom: none;'>
                            <span class='pmis-label'>📋 Loan App:</span>
                            <span class='pmis-val'>" . e($appNo) . "</span>
                        </div>
                        <div class='pmis-pwd-box'>
                            <div class='pmis-pwd-heading'>🔑 Password to Extract (Member ID):</div>
                            <div class='pmis-pwd-code' title='Click to highlight and copy'>" . e($memberNo) . "</div>
                        </div>
                        <div class='pmis-hint'>
                            Use the <strong style='color: inherit;'>Member ID</strong> above as the password when opening or extracting the ZIP file using 7-Zip, WinRAR, or Windows Extractor.
                        </div>
                    </div>
                ");
            })
            ->modalSubmitActionLabel('Download Archive')
            ->modalCancelActionLabel('Cancel')
            ->action(function (LoanApplication $record, $livewire) {
                $downloadUrl = route('loans.download-archive', ['record' => $record->id]);
                $password = $record->member?->member_no ?? 'MEMBER-GENERAL';

                \Filament\Notifications\Notification::make()
                    ->title('📦 Archive Downloading')
                    ->body("Password: {$password}")
                    ->success()
                    ->send();

                $livewire->js("window.location.href = '{$downloadUrl}';");
            });
    }

    public static function getViewDocumentsTableAction(): Action
    {
        return Action::make('viewDocuments')
            ->label('View Scanned Documents')
            ->icon('heroicon-o-eye')
            ->color('info')
            ->slideOver()
            ->modalWidth('7xl')
            ->modalHeading(fn (LoanApplication $record): string => "Scanned Documents — {$record->application_no} " . ($record->member ? "({$record->member->full_name})" : ''))
            ->modalDescription('Interactive slide-over document viewer with zoom, 90° rotation, print, and downloads')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close Viewer')
            ->modalContent(fn (LoanApplication $record): \Illuminate\Contracts\View\View => view('filament.modals.document-slideover-viewer', [
                'record' => $record,
            ]));
    }

    public static function getQuickUploadTableAction(): Action
    {
        return Action::make('quickUploadDocument')
            ->label('Quick Upload Document')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('warning')
            ->slideOver()
            ->modalWidth('2xl')
            ->modalHeading(fn (LoanApplication $record): string => "⚡ Quick Document Upload — {$record->application_no}")
            ->modalDescription(fn (LoanApplication $record): string => "Directly upload an agency receipt (BIR, LTO, PPSR) or updated document for " . ($record->member?->full_name ?? 'Borrower') . " without re-uploading other files.")
            ->modalSubmitActionLabel('Upload & Attach Document')
            ->fillForm(fn (LoanApplication $record): array => [
                'document_type'              => 'BIR',
                'bir_reference_no'           => $record->bir_reference_no,
                'bir_amount_paid'            => $record->bir_amount_paid,
                'notary_doc_no'              => $record->notary_doc_no,
                'notary_page_no'             => $record->notary_page_no,
                'notary_book_no'             => $record->notary_book_no,
                'notary_series_no'           => $record->notary_series_no ?? (string) now()->year,
                'ppsr_notice_no'             => $record->ppsr_notice_no,
                'ppsr_payment_date'          => $record->ppsr_payment_date,
                'ppsr_initial_notice_date'   => $record->ppsr_initial_notice_date,
                'ppsr_ctc_notice_date'       => $record->ppsr_ctc_notice_date,
                'rod_entry_no'               => $record->rod_entry_no,
                'rod_payment_date'           => $record->rod_payment_date,
                'rod_amount_paid'            => $record->rod_amount_paid,
                'lto_or_no'                  => $record->lto_or_no,
                'lto_payment_date'           => $record->lto_payment_date,
                'lto_amount_paid'            => $record->lto_amount_paid,
                'cancellation_mode'          => $record->cancellation_mode ?? 'member',
                'cancellation_entry_no'      => $record->cancellation_entry_no,
                'cancellation_or_no'         => $record->cancellation_or_no,
                'cancellation_date'          => $record->cancellation_date,
                'title_released_date'        => $record->title_released_date,
                'title_released_to'          => $record->title_released_to,
                'title_release_remarks'      => $record->title_release_remarks,
            ])
            ->form([
                Select::make('document_type')
                    ->label('Document Type / Agency')
                    ->options([
                        'BIR'          => '🏛️ BIR — EFPS / DST Receipt',
                        'CONTRACT'     => '📄 Notarized Contract (Chattel Mortgage / Real Estate Mortgage)',
                        'PN'           => '📝 Notarized Promissory Note / Sworn Undertaking',
                        'ROD'          => '🏢 ROD — Registry of Deeds Entry / Receipt',
                        'PPSR'         => '🛡️ PPSR — Security Notice / Registration Receipt',
                        'LTO'          => '🚗 LTO — Official Receipt (OR) / Certificate of Registration (CR)',
                        'CANCELLATION' => '🔓 Cancellation / Release of Mortgage / Return Receipt',
                        'KYC'          => '👤 Borrower KYC / Updated Valid ID',
                        'COLLATERAL'   => '🚘 Collateral / Stencil / Unit Photo',
                        'OTHER'        => '📎 Other Document / Memo',
                    ])
                    ->default('BIR')
                    ->required()
                    ->live(),

                // ── BIR Fields ──
                Grid::make(['default' => 1, 'md' => 2])
                    ->visible(fn ($get) => $get('document_type') === 'BIR')
                    ->schema([
                        TextInput::make('bir_reference_no')
                            ->label('eFPS Reference Number')
                            ->placeholder('e.g. 652600074997564'),
                        TextInput::make('bir_amount_paid')
                            ->label('DST Amount Paid (₱)')
                            ->prefix('₱')
                            ->placeholder('0.00')
                            ->numeric(),
                    ]),

                // ── Notary Fields (Contract / Promissory Note) ──
                Grid::make(['default' => 2, 'md' => 4])
                    ->visible(fn ($get) => in_array($get('document_type'), ['CONTRACT', 'PN']))
                    ->schema([
                        TextInput::make('notary_doc_no')
                            ->label('Doc. No.')
                            ->placeholder('e.g. 142'),
                        TextInput::make('notary_page_no')
                            ->label('Page No.')
                            ->placeholder('e.g. 29'),
                        TextInput::make('notary_book_no')
                            ->label('Book No.')
                            ->placeholder('e.g. XII or 15'),
                        TextInput::make('notary_series_no')
                            ->label('Series of')
                            ->placeholder('e.g. ' . now()->year),
                    ]),

                // ── PPSR Fields ──
                Grid::make(['default' => 1, 'md' => 2])
                    ->visible(fn ($get) => $get('document_type') === 'PPSR')
                    ->schema([
                        TextInput::make('ppsr_notice_no')
                            ->label('Notice Registration #:')
                            ->placeholder('e.g. PPSR-260724001343'),
                        DatePicker::make('ppsr_payment_date')
                            ->label('Date of Payment / Registration:'),
                        DatePicker::make('ppsr_initial_notice_date')
                            ->label('Initial Notice Registration Date'),
                        DatePicker::make('ppsr_ctc_notice_date')
                            ->label('Certified True Copy of Notice Registration Date'),
                    ]),

                // ── ROD Fields ──
                Grid::make(['default' => 1, 'md' => 3])
                    ->visible(fn ($get) => $get('document_type') === 'ROD')
                    ->schema([
                        TextInput::make('rod_entry_no')
                            ->label('Ref No. / EPEB Entry #:')
                            ->placeholder('e.g. EPEB496/2026001432'),
                        DatePicker::make('rod_payment_date')
                            ->label('Date of Payment / Registration:'),
                        TextInput::make('rod_amount_paid')
                            ->label('ROD Amount Paid (₱):')
                            ->prefix('₱')
                            ->placeholder('0.00')
                            ->numeric(),
                    ]),

                // ── LTO Fields ──
                Grid::make(['default' => 1, 'md' => 3])
                    ->visible(fn ($get) => $get('document_type') === 'LTO')
                    ->schema([
                        TextInput::make('lto_or_no')
                            ->label('Official Receipt No. (OR No.)')
                            ->placeholder('e.g. OR-2026-009876'),
                        DatePicker::make('lto_payment_date')
                            ->label('Date Paid / Registered'),
                        TextInput::make('lto_amount_paid')
                            ->label('Amount Paid (₱)')
                            ->prefix('₱')
                            ->placeholder('0.00')
                            ->numeric(),
                    ]),

                // ── Cancellation / Release Fields ──
                Grid::make(1)
                    ->visible(fn ($get) => $get('document_type') === 'CANCELLATION')
                    ->schema([
                        Radio::make('cancellation_mode')
                            ->label('Processing Mode')
                            ->options([
                                'member' => '👤 Member-Handled (Si Member ang maglalakad sa ROD/LTO)',
                                'coop'   => '🏢 Office-Assisted (Gisalig sa Opisina / Tayo ang maglalakad)',
                            ])
                            ->default('member')
                            ->live()
                            ->inline(),

                        // If Member-Handled
                        Grid::make(['default' => 1, 'md' => 3])
                            ->visible(fn ($get) => ($get('cancellation_mode') ?? 'member') === 'member')
                            ->schema([
                                DatePicker::make('title_released_date')
                                    ->label('Date Documents Handed Over'),
                                TextInput::make('title_released_to')
                                    ->label('Receiver Full Name')
                                    ->placeholder('e.g. Juan Dela Cruz'),
                                TextInput::make('title_release_remarks')
                                    ->label('Receipt / Remarks')
                                    ->placeholder('e.g. AR #88219 (Docs issued)'),
                            ]),

                        // If Office-Assisted
                        Grid::make(['default' => 1, 'md' => 3])
                            ->visible(fn ($get) => $get('cancellation_mode') === 'coop')
                            ->schema([
                                TextInput::make('cancellation_entry_no')
                                    ->label('Cancellation Entry / ROD Entry #')
                                    ->placeholder('e.g. 2026-CAN-00045'),
                                TextInput::make('cancellation_or_no')
                                    ->label('ROD Cancellation OR #')
                                    ->placeholder('e.g. OR-ROD-88912'),
                                DatePicker::make('cancellation_date')
                                    ->label('Date Cancellation Filed'),
                                DatePicker::make('title_released_date')
                                    ->label('Date Title Returned to Member'),
                                TextInput::make('title_released_to')
                                    ->label('Released To (Receiver Name)')
                                    ->placeholder('Borrower name or Authorized Rep'),
                                TextInput::make('title_release_remarks')
                                    ->label('Receipt / Remarks')
                                    ->placeholder('e.g. AR #88219'),
                            ]),
                    ]),

                // ── General Reference for KYC / Collateral / Other ──
                TextInput::make('reference_no')
                    ->label('Reference No. / Note (Optional)')
                    ->placeholder('e.g. Valid ID No., Remarks, etc.')
                    ->visible(fn ($get) => in_array($get('document_type'), ['KYC', 'COLLATERAL', 'OTHER'])),

                FileUpload::make('attachment')
                    ->label('Select File(s) (.jpg, .jpeg, .png, .webp, .pdf)')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/webp'])
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth('1200')
                    ->imageResizeTargetHeight('1200')
                    ->imageResizeUpscale(false)
                    ->disk('public')
                    ->directory('quick-document-uploads')
                    ->preserveFilenames(false)
                    ->multiple()
                    ->reorderable()
                    ->panelLayout('grid')
                    ->imagePreviewHeight('150px')
                    ->required()
                    ->helperText('You can select or drag multiple files (e.g. Front & Back ID, multiple contract pages, or multiple photos).'),
            ])
            ->action(function (LoanApplication $record, array $data, $livewire): void {
                $docType = $data['document_type'];
                $uploadedFiles = (array) ($data['attachment'] ?? []);

                if (empty($uploadedFiles)) {
                    Notification::make()
                        ->title('Upload Error')
                        ->body('No files were uploaded.')
                        ->danger()
                        ->send();
                    return;
                }

                $refNo = '';
                $updateData = [];

                if ($docType === 'BIR') {
                    if (isset($data['bir_reference_no']) && filled($data['bir_reference_no'])) {
                        $updateData['bir_reference_no'] = trim($data['bir_reference_no']);
                        $refNo = trim($data['bir_reference_no']);
                    }
                    if (isset($data['bir_amount_paid']) && filled($data['bir_amount_paid'])) {
                        $updateData['bir_amount_paid'] = $data['bir_amount_paid'];
                    }
                } elseif (in_array($docType, ['CONTRACT', 'PN'])) {
                    if (isset($data['notary_doc_no']) && filled($data['notary_doc_no'])) {
                        $updateData['notary_doc_no'] = trim($data['notary_doc_no']);
                    }
                    if (isset($data['notary_page_no']) && filled($data['notary_page_no'])) {
                        $updateData['notary_page_no'] = trim($data['notary_page_no']);
                    }
                    if (isset($data['notary_book_no']) && filled($data['notary_book_no'])) {
                        $updateData['notary_book_no'] = trim($data['notary_book_no']);
                    }
                    if (isset($data['notary_series_no']) && filled($data['notary_series_no'])) {
                        $updateData['notary_series_no'] = trim($data['notary_series_no']);
                    }
                    $notaryParts = array_filter([
                        filled($data['notary_doc_no'] ?? null) ? 'Doc #' . $data['notary_doc_no'] : null,
                        filled($data['notary_book_no'] ?? null) ? 'Bk #' . $data['notary_book_no'] : null,
                    ]);
                    $refNo = implode(', ', $notaryParts);
                } elseif ($docType === 'PPSR') {
                    if (isset($data['ppsr_notice_no']) && filled($data['ppsr_notice_no'])) {
                        $updateData['ppsr_notice_no'] = trim($data['ppsr_notice_no']);
                        $refNo = trim($data['ppsr_notice_no']);
                    }
                    if (isset($data['ppsr_payment_date']) && filled($data['ppsr_payment_date'])) {
                        $updateData['ppsr_payment_date'] = $data['ppsr_payment_date'];
                    }
                    if (isset($data['ppsr_initial_notice_date']) && filled($data['ppsr_initial_notice_date'])) {
                        $updateData['ppsr_initial_notice_date'] = $data['ppsr_initial_notice_date'];
                    }
                    if (isset($data['ppsr_ctc_notice_date']) && filled($data['ppsr_ctc_notice_date'])) {
                        $updateData['ppsr_ctc_notice_date'] = $data['ppsr_ctc_notice_date'];
                    }
                } elseif ($docType === 'ROD') {
                    if (isset($data['rod_entry_no']) && filled($data['rod_entry_no'])) {
                        $updateData['rod_entry_no'] = trim($data['rod_entry_no']);
                        $refNo = trim($data['rod_entry_no']);
                    }
                    if (isset($data['rod_payment_date']) && filled($data['rod_payment_date'])) {
                        $updateData['rod_payment_date'] = $data['rod_payment_date'];
                    }
                    if (isset($data['rod_amount_paid']) && filled($data['rod_amount_paid'])) {
                        $updateData['rod_amount_paid'] = $data['rod_amount_paid'];
                    }
                } elseif ($docType === 'LTO') {
                    if (isset($data['lto_or_no']) && filled($data['lto_or_no'])) {
                        $updateData['lto_or_no'] = trim($data['lto_or_no']);
                        $refNo = trim($data['lto_or_no']);
                    }
                    if (isset($data['lto_payment_date']) && filled($data['lto_payment_date'])) {
                        $updateData['lto_payment_date'] = $data['lto_payment_date'];
                    }
                    if (isset($data['lto_amount_paid']) && filled($data['lto_amount_paid'])) {
                        $updateData['lto_amount_paid'] = $data['lto_amount_paid'];
                    }
                } elseif ($docType === 'CANCELLATION') {
                    $mode = $data['cancellation_mode'] ?? 'member';
                    $updateData['cancellation_mode'] = $mode;

                    if (isset($data['title_released_date']) && filled($data['title_released_date'])) {
                        $updateData['title_released_date'] = $data['title_released_date'];
                    }
                    if (isset($data['title_released_to']) && filled($data['title_released_to'])) {
                        $updateData['title_released_to'] = trim($data['title_released_to']);
                    }
                    if (isset($data['title_release_remarks']) && filled($data['title_release_remarks'])) {
                        $updateData['title_release_remarks'] = trim($data['title_release_remarks']);
                    }

                    if ($mode === 'member') {
                        $updateData['mortgage_release_status'] = 'released_self_cancellation';
                        $updateData['is_title_returned'] = true;
                        $updateData['is_cancellation_deed_returned'] = true;
                        $refNo = !empty($data['title_release_remarks']) ? trim($data['title_release_remarks']) : 'Self-Cancellation';
                    } else {
                        if (isset($data['cancellation_entry_no']) && filled($data['cancellation_entry_no'])) {
                            $updateData['cancellation_entry_no'] = trim($data['cancellation_entry_no']);
                            $refNo = trim($data['cancellation_entry_no']);
                        }
                        if (isset($data['cancellation_or_no']) && filled($data['cancellation_or_no'])) {
                            $updateData['cancellation_or_no'] = trim($data['cancellation_or_no']);
                        }
                        if (isset($data['cancellation_date']) && filled($data['cancellation_date'])) {
                            $updateData['cancellation_date'] = $data['cancellation_date'];
                        }
                        $updateData['mortgage_release_status'] = 'cancelled_released';
                        if (empty($refNo)) {
                            $refNo = !empty($data['cancellation_or_no']) ? trim($data['cancellation_or_no']) : 'Office-Cancellation';
                        }
                    }
                } else {
                    $refNo = !empty($data['reference_no']) ? trim($data['reference_no']) : '';
                }

                // Auto-sync entered agency & tracking fields directly to loan record
                if (!empty($updateData)) {
                    $record->update($updateData);
                }

                $uploadedCount = 0;
                $slugRef = !empty($refNo) ? '_' . \Illuminate\Support\Str::slug($refNo) : '';
                $timestamp = date('Ymd_His');
                $totalFiles = count($uploadedFiles);

                foreach ($uploadedFiles as $index => $uploadedRelativePath) {
                    $fullDiskPath = \Illuminate\Support\Facades\Storage::disk('public')->path($uploadedRelativePath);

                    if (!file_exists($fullDiskPath)) {
                        continue;
                    }

                    $ext = pathinfo($fullDiskPath, PATHINFO_EXTENSION);
                    $suffix = $totalFiles > 1 ? "_" . ($index + 1) : '';
                    $cleanFileName = "{$docType}{$slugRef}_{$timestamp}{$suffix}.{$ext}";

                    $displayName = $totalFiles > 1
                        ? "{$docType} Document " . (!empty($refNo) ? "({$refNo})" : date('F d, Y')) . " (Part " . ($index + 1) . ")"
                        : "{$docType} Document " . (!empty($refNo) ? "({$refNo})" : date('F d, Y'));

                    $mediaAdder = $record->addMedia($fullDiskPath);
                    $mediaAdder
                        ->usingFileName($cleanFileName)
                        ->usingName($displayName)
                        ->withCustomProperties([
                            'document_type' => $docType,
                            'reference_no'  => $refNo,
                            'uploaded_by'   => auth()->user()?->name ?? 'User',
                        ])
                        ->toMediaCollection('loan_application_documents', 'public');

                    $uploadedCount++;
                }

                $fileCountText = $uploadedCount > 1 ? "{$uploadedCount} documents" : "Document";

                Notification::make()
                    ->title('⚡ Upload Successful!')
                    ->body("{$fileCountText} categorized as {$docType} attached to Application {$record->application_no} and tracking details updated.")
                    ->success()
                    ->send();

                if (method_exists($livewire, 'dispatch')) {
                    $livewire->dispatch('$refresh');
                }
            });
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
                $sheet->setTitle('Loan Applications');

                $headers = [
                    'Date Filed',
                    'Application ID',
                    'Borrower Name',
                    'Member No.',
                    'CID',
                    'OIC Branch',
                    'Loan Amount',
                    'Collateral Type',
                    'Approval Status',
                    'Stage / Status',
                    'Aging in Stage',
                    'Deficiency / Sub-Status',
                    'Application Notes / HO Assessment',
                    'Loan Account No.',
                    'Date Granted',
                    'Maturity Date',
                ];

                $sheet->fromArray([$headers], null, 'A1');

                // Header styling: Emerald green, bold white text, centered
                $sheet->getStyle('A1:P1')->applyFromArray([
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
                    $record->loadMissing(['member', 'branchOffice']);

                    $collateral = match (true) {
                        str_contains(strtolower((string) $record->collateral_type), 'chattel') || str_contains(strtolower((string) $record->collateral_type), 'cm') => 'Chattel Mortgage (CM)',
                        str_contains(strtolower((string) $record->collateral_type), 'real_estate') || str_contains(strtolower((string) $record->collateral_type), 'rem') => 'Real Estate Mortgage (REM)',
                        default => ucwords(str_replace('_', ' ', (string) $record->collateral_type)),
                    };

                    $daysInStage = $record->status_changed_at
                        ? (int) now()->diffInDays($record->status_changed_at)
                        : (int) now()->diffInDays($record->created_at);
                    $agingText = $daysInStage === 0 ? 'Today' : "{$daysInStage} day(s)";

                    $deficiencyText = match ($record->sub_status) {
                        'internal_deficiency' => 'INTERNAL DEFICIENCY',
                        'external_deficiency' => 'EXTERNAL DEFICIENCY',
                        default => 'CLEAN / ON PROCESS',
                    };

                    $dataRow = [
                        $record->created_at ? $record->created_at->format('F d, Y') : '—',
                        $record->application_no ?? '—',
                        $record->member?->full_name ?? '—',
                        $record->member?->member_no ?? '—',
                        $record->member?->cid ?? '—',
                        $record->branchOffice?->name ?? '—',
                        (float) ($record->requested_amount ?? 0),
                        $collateral,
                        $record->approval_status ?? 'Pending',
                        $record->status ?? 'Initial Assessment',
                        $agingText,
                        $deficiencyText,
                        $record->deficiency_notes ?: ($record->notes ?: '—'),
                        $record->loan_account_no ?? '—',
                        $record->date_granted ? $record->date_granted->format('F d, Y') : '—',
                        $record->maturity_date ? $record->maturity_date->format('F d, Y') : '—',
                    ];

                    $sheet->fromArray([$dataRow], null, "A{$row}", true);

                    // Currency format for Loan Amount (Col G)
                    $sheet->getStyle("G{$row}")
                        ->getNumberFormat()
                        ->setFormatCode('₱#,##0.00;([₱#,##0.00]);"-"');

                    // Center-align dates and IDs
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("O{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("P{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Alternating row background for clean readability
                    if ($row % 2 === 1) {
                        $sheet->getStyle("A{$row}:P{$row}")
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
                    $sheet->getStyle("A1:P{$lastRow}")
                        ->getBorders()
                        ->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()
                        ->setRGB('E2E8F0');
                }

                foreach (range('A', 'P') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $fileName = 'Loan_Applications_Export_' . now()->format('Y-m-d_His') . '.xlsx';

                return response()->streamDownload(function () use ($spreadsheet) {
                    $writer = new Xlsx($spreadsheet);
                    $writer->save('php://output');
                }, $fileName, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]);
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function getCreateTransmittalBulkAction(): BulkAction
    {
        return BulkAction::make('generate_transmittal')
            ->label('Generate Mortgage Transmittal')
            ->icon('heroicon-o-printer')
            ->color('success')
            ->modalHeading('Official Mortgage Transmittal Generator')
            ->modalDescription('Awtomatikong kukuwentahin ang statutory fees (ROD/LTO, BIR DST, HO Fee, Misc, SPA) at bubuuin ang opisyal na Oro Integrated Cooperative transmittal.')
            ->modalSubmitActionLabel('Print Transmittal Slip')
            ->form([
                DatePicker::make('transmittal_date')
                    ->label('Transmittal Date')
                    ->default(now())
                    ->required(),
                TextInput::make('checked_by')
                    ->label('Checked By (Bookkeeper)')
                    ->placeholder('e.g. Maria Santos (Bookkeeper)')
                    ->default('Bookkeeper'),
                TextInput::make('approved_by')
                    ->label('Approved By (Branch Manager)')
                    ->placeholder('e.g. Juan dela Cruz (Branch Manager)')
                    ->default('Branch Manager'),
                TextInput::make('courier_details')
                    ->label('Courier / Delivery Handling')
                    ->placeholder('e.g. Liaison Officer / Hand Carried / LBC')
                    ->default('Liaison Officer / Hand Carried'),
            ])
            ->extraModalFooterActions([
                Action::make('download_excel')
                    ->label('Download Official Excel (.xlsx)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                        if ($records->isEmpty()) {
                            return;
                        }
                        $ids = $records->pluck('id')->implode(',');
                        $url = route('transmittals.generate-excel', [
                            'ids'         => $ids,
                            'date'        => $data['transmittal_date'] ?? now()->format('Y-m-d'),
                            'checked_by'  => $data['checked_by'] ?? 'Bookkeeper',
                            'approved_by' => $data['approved_by'] ?? 'Branch Manager',
                            'courier'     => $data['courier_details'] ?? 'Liaison Officer / Hand Carried',
                        ]);
                        return redirect()->away($url);
                    }),
            ])
            ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                if ($records->isEmpty()) {
                    return;
                }
                $ids = $records->pluck('id')->implode(',');
                $url = route('transmittals.generate-print', [
                    'ids'         => $ids,
                    'date'        => $data['transmittal_date'] ?? now()->format('Y-m-d'),
                    'checked_by'  => $data['checked_by'] ?? 'Bookkeeper',
                    'approved_by' => $data['approved_by'] ?? 'Branch Manager',
                    'courier'     => $data['courier_details'] ?? 'Liaison Officer / Hand Carried',
                ]);
                return redirect()->away($url);
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date Filed')
                    ->dateTime('F d, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('application_no')
                    ->label('Application ID')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Application ID copied to clipboard!')
                    ->copyMessageDuration(1500)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('member.last_name')
                    ->label('Borrower Name')
                    ->formatStateUsing(fn ($state, $record) => $record?->member?->full_name ?? $state ?? '—')
                    ->description(fn ($record) => $record->branchOffice?->name)
                    ->copyable()
                    ->copyableState(fn ($record) => $record->member?->full_name)
                    ->copyMessage('Borrower name copied to clipboard!')
                    ->copyMessageDuration(1500)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('requested_amount')
                    ->label(function ($livewire = null): string {
                        $resource = (is_object($livewire) && method_exists($livewire, 'getResource'))
                            ? $livewire::getResource()
                            : null;

                        if ($resource === \App\Filament\Resources\ForApprovalResource::class
                            || $resource === \App\Filament\Resources\DisapprovedLoanResource::class
                            || request()->routeIs('*.for-approvals.*')
                            || request()->routeIs('*.disapproved-loans.*')) {
                            return 'Requested Amount';
                        }

                        return 'Loan Amount';
                    })
                    ->money('PHP')
                    ->sortable(),
                Tables\Columns\TextColumn::make('collateral_type')
                    ->label('Collateral Type')
                    ->badge()
                    ->color(fn ($state) => (str_contains(strtolower((string)$state), 'chattel') || str_contains(strtolower((string)$state), 'cm')) ? 'warning' : 'primary')
                    ->formatStateUsing(fn ($state) => (str_contains(strtolower((string)$state), 'chattel') || str_contains(strtolower((string)$state), 'cm')) ? 'Chattel (CM)' : 'Real Estate (REM)')
                    ->description(function (LoanApplication $record): ?\Illuminate\Support\HtmlString {
                        $isChattel = str_contains(strtolower((string)$record->collateral_type), 'chattel') || str_contains(strtolower((string)$record->collateral_type), 'cm');
                        $count = $isChattel ? $record->chattelCollaterals->count() : $record->realEstateCollaterals->count();
                        $countText = $count . ' ' . ($count === 1 ? 'Collateral' : 'Collaterals');

                        if ($isChattel) {
                            $statuses = $record->chattelCollaterals->map(function ($item) {
                                return match ($item->encumbered_to) {
                                    'OIC ENCUMBERED', 'Encumbered' => 'OIC ENCUMBERED',
                                    'REL/E' => 'REL/E',
                                    default => 'NEW',
                                };
                            })->filter()->unique()->implode(', ');
                            $colStatus = !empty($statuses) ? $statuses : 'NEW';
                        } else {
                            $statuses = $record->realEstateCollaterals->map(function ($item) {
                                return match ($item->rem_status) {
                                    'OIC Encumbered', 'Encumbered' => 'OIC Encumbered',
                                    'REL/E' => 'REL/E',
                                    default => 'New',
                                };
                            })->filter()->unique()->implode(', ');
                            $colStatus = !empty($statuses) ? $statuses : 'New';
                        }

                        $custodyInfo = '';
                        if ($record->mortgage_release_status === 'cancelled_released') {
                            $custodyInfo = " • <span style='color: #10b981; font-weight: 600;'>Title Returned (Cancelled)</span>";
                        } elseif ($record->mortgage_release_status === 'released_self_cancellation') {
                            $custodyInfo = " • <span style='color: #0284c7; font-weight: 600;'>Docs Issued (Self-Cancel)</span>";
                        } elseif ($record->mortgage_release_status === 'for_cancellation') {
                            $custodyInfo = " • <span style='color: #f59e0b; font-weight: 600;'>For ROD Cancel</span>";
                        } elseif (filled($record->title_released_date)) {
                            $custodyInfo = " • <span style='color: #10b981; font-weight: 600;'>Title Returned</span>";
                        } elseif (filled($record->vault_location)) {
                            $vaultSafe = e($record->vault_location);
                            $custodyInfo = " • <span>Vault: {$vaultSafe}</span>";
                        }

                        return new \Illuminate\Support\HtmlString("<span style='font-size: 10px; font-weight: 500; opacity: 0.85; display: block; margin-top: 2px;'>{$countText} • {$colStatus}{$custodyInfo}</span>");
                    }),
                Tables\Columns\TextColumn::make('approval_status')
                    ->label('Approval Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Approved'       => 'success',
                        'Branch Updated' => 'warning',
                        'Disapproved'    => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(function ($state, LoanApplication $record) {
                        if (in_array($record->sub_status, ['internal_deficiency', 'external_deficiency'])) {
                            return 'danger';
                        }
                        static $statusColorMap = null;
                        if ($statusColorMap === null) {
                            $statusColorMap = \Illuminate\Support\Facades\Cache::remember('application_status_colors', 300, function () {
                                return \App\Models\ApplicationStatus::pluck('color', 'name')->toArray();
                            });
                        }
                        return $statusColorMap[$state] ?? 'info';
                    })
                    ->description(function (LoanApplication $record): ?\Illuminate\Support\HtmlString {
                        $html = '';

                        if ($record->sub_status === 'internal_deficiency') {
                            $html .= "<span style='font-size: 10px; font-weight: 600; color: #ef4444; display: block; margin-top: 2px;'>🏢 Internal Deficiency</span>";
                        } elseif ($record->sub_status === 'external_deficiency') {
                            $html .= "<span style='font-size: 10px; font-weight: 600; color: #ef4444; display: block; margin-top: 2px;'>🏛️ External Deficiency</span>";
                        }

                        $ref = $record->status_changed_at ? \Carbon\Carbon::parse($record->status_changed_at) : ($record->created_at ? \Carbon\Carbon::parse($record->created_at) : now());
                        $days = (int) $ref->diffInDays(now());

                        if ($days >= 11) {
                            $color = '#ef4444';
                            $icon = '🔴';
                        } elseif ($days >= 6) {
                            $color = '#f59e0b';
                            $icon = '🟡';
                        } else {
                            $color = '#22c55e';
                            $icon = '🟢';
                        }

                        $dayText = $days === 0 ? 'today' : ($days === 1 ? '1 day' : "{$days} days");
                        $html .= "<span style='font-size: 10px; font-weight: 500; color: {$color}; display: block; margin-top: 2px;'>{$icon} {$dayText} in this stage</span>";

                        return new \Illuminate\Support\HtmlString($html);
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Application Notes / HO Assessment')
                    ->wrap()
                    ->limit(65)
                    ->tooltip(fn (LoanApplication $record): ?string => $record->notes)
                    ->searchable()
                    ->placeholder('—')
                    ->description(function (LoanApplication $record): ?string {
                        $defNotes = $record->deficiency_notes ?: ($record->notes ?: null);
                        $shortNotes = $defNotes ? \Illuminate\Support\Str::limit($defNotes, 60) : 'Deficiency noted';
                        if ($record->sub_status === 'internal_deficiency') {
                            return '🏢 Internal Deficiency: ' . $shortNotes;
                        } elseif ($record->sub_status === 'external_deficiency') {
                            return '🏛️ External Deficiency: ' . $shortNotes;
                        }

                        return null;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('loan_account_no')
                    ->label('Account No.')
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('Account No. copied to clipboard!')
                    ->copyMessageDuration(1500)
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('bir_or_no')
                    ->label('BIR OR No.')
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('BIR OR No. copied to clipboard!')
                    ->copyMessageDuration(1500)
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('rod_entry_no')
                    ->label('ROD Entry No.')
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('ROD Entry No. copied to clipboard!')
                    ->copyMessageDuration(1500)
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('annotation_no')
                    ->label('Annotation No.')
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('Annotation No. copied to clipboard!')
                    ->copyMessageDuration(1500)
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('approval_status')
                    ->label('Approval Status')
                    ->options([
                        'Pending Approval' => 'Pending Approval',
                        'Approved'         => 'Approved',
                        'Branch Updated'   => 'Branch Updated',
                        'Disapproved'      => 'Disapproved',
                    ]),
                Tables\Filters\SelectFilter::make('collateral_type')
                    ->label('Collateral Type')
                    ->options([
                        'real_estate_mortgage' => 'Real Estate Mortgage (REM)',
                        'rem_certification'    => 'REM Certification',
                        'rem_amendment'        => 'REM Amendment',
                        'chattel_mortgage'     => 'Chattel Mortgage (CM)',
                        'cm_certification'     => 'CM Certification',
                        'cm_amendment'         => 'CM Amendment',
                    ]),
                Tables\Filters\SelectFilter::make('branch_office_id')
                    ->label('OIC Branch Office')
                    ->relationship('branchOffice', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Application Stage')
                    ->options(function () {
                        return Cache::remember('active_application_statuses_pluck', 600, function () {
                            return \App\Models\ApplicationStatus::query()
                                ->where('is_active', true)
                                ->orderBy('sort_order')
                                ->pluck('name', 'name')
                                ->toArray();
                        });
                    }),
                Tables\Filters\SelectFilter::make('sub_status')
                    ->label('Verification / Condition')
                    ->options([
                        'none'                => '🟢 Standard (No Flags)',
                        'internal_deficiency' => '🏢 Internal Deficiency',
                        'external_deficiency' => '🏛️ External Deficiency',
                    ]),
                Tables\Filters\SelectFilter::make('aging_sla')
                    ->label('Aging in Stage')
                    ->options([
                        'normal'          => '🟢 1–5 days',
                        'needs_attention' => '🟡 5–10 days',
                        'overdue'         => '🔴 11+ days',
                    ])
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? null;
                        if (! $value) {
                            return;
                        }

                        $date11 = now()->subDays(11);
                        $date5  = now()->subDays(5);

                        if ($value === 'overdue') {
                            $query->where(function ($q) use ($date11) {
                                $q->where('status_changed_at', '<=', $date11)
                                  ->orWhere(fn ($sq) => $sq->whereNull('status_changed_at')->where('created_at', '<=', $date11));
                            });
                        } elseif ($value === 'needs_attention') {
                            $query->where(function ($q) use ($date11, $date5) {
                                $q->where(function ($sub) use ($date11, $date5) {
                                    $sub->where('status_changed_at', '>', $date11)
                                        ->where('status_changed_at', '<=', $date5);
                                })->orWhere(function ($sub) use ($date11, $date5) {
                                    $sub->whereNull('status_changed_at')
                                        ->where('created_at', '>', $date11)
                                        ->where('created_at', '<=', $date5);
                                });
                            });
                        } elseif ($value === 'normal') {
                            $query->where(function ($q) use ($date5) {
                                $q->where('status_changed_at', '>', $date5)
                                  ->orWhere(fn ($sq) => $sq->whereNull('status_changed_at')->where('created_at', '>', $date5));
                            });
                        }
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    static::getViewDocumentsTableAction(),
                    static::getQuickUploadTableAction(),
                    static::getApproveTableAction(),
                    static::getDisapproveTableAction(),
                    static::getDownloadArchiveTableAction(),
                    EditAction::make()
                        ->url(fn (LoanApplication $record): string => LoanApplicationResource::getUrl('edit', ['record' => $record])),
                    DeleteAction::make()
                        ->visible(fn (LoanApplication $record): bool => auth()->user()?->can('Delete:LoanApplication') && (auth()->user()?->isHeadOfficeUser() || auth()->user()?->hasRole(['super_admin', 'Super Admin']))),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    static::getCreateTransmittalBulkAction(),
                    static::getExportToExcelBulkAction(),
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
            'index' => Pages\ListLoanApplications::route('/'),
            'create' => Pages\CreateLoanApplication::route('/create'),
            'view' => Pages\ViewLoanApplication::route('/{record}'),
            'edit' => Pages\EditLoanApplication::route('/{record}/edit'),
        ];
    }
}
