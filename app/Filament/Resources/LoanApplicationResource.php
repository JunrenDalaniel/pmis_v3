<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoanApplicationResource\Pages;
use App\Filament\Concerns\HandlesApprovalWorkflow;
use App\Models\LoanApplication;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use ZipArchive;
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

    protected static ?string $recordTitleAttribute = 'application_no';

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
        $nonReleasedStages = [
            'Initial Assessment',
            'For Notary',
            'Notarized',
            'For BIR', 'For Bir',
            'BIR Paid', 'Bir Paid',
            'For ROD', 'For Rod',
        ];

        $query = parent::getEloquentQuery()
            ->where('approval_status', 'Approved')
            ->whereNotIn('status', $nonReleasedStages)
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

    public static function getGloballySearchableAttributes(): array
    {
        return ['application_no', 'member.first_name', 'member.last_name', 'member.member_no'];
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
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                        ->searchable(['member_no', 'first_name', 'last_name', 'middle_name'])
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
                            Grid::make(3)->schema([
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
                            Grid::make(3)->schema([
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

                    // Display All Member Details Card
                    Section::make('Complete Member Profile Information')
                        ->description('All recorded personal identity, contact, and membership details')
                        ->visible(fn ($get) => filled($get('member_id')))
                        ->schema([
                            Grid::make(4)->schema([
                                Placeholder::make('member_no_disp')
                                    ->label('Member No. (IPI No.)')
                                    ->content(fn ($get) => \App\Models\Member::find($get('member_id'))?->member_no ?? '—'),

                                Placeholder::make('full_name_disp')
                                    ->label('Full Name')
                                    ->content(fn ($get) => \App\Models\Member::find($get('member_id'))?->full_name ?? '—'),

                                Placeholder::make('birth_date_disp')
                                    ->label('Date of Birth')
                                    ->content(fn ($get) => \App\Models\Member::find($get('member_id'))?->birth_date?->format('M d, Y') ?? '—'),

                                Placeholder::make('sex_disp')
                                    ->label('Sex')
                                    ->content(fn ($get) => \App\Models\Member::find($get('member_id'))?->sex ?? '—'),
                            ]),
                            Grid::make(5)->schema([
                                Placeholder::make('civil_status_disp')
                                    ->label('Civil Status')
                                    ->content(fn ($get) => \App\Models\Member::find($get('member_id'))?->civil_status ?? '—'),

                                Placeholder::make('tin_disp')
                                    ->label('TIN')
                                    ->content(fn ($get) => \App\Models\Member::find($get('member_id'))?->tin ?? '—'),

                                Placeholder::make('phone_disp')
                                    ->label('Phone Number')
                                    ->content(fn ($get) => \App\Models\Member::find($get('member_id'))?->phone ?? '—'),

                                Placeholder::make('email_disp')
                                    ->label('Email Address')
                                    ->content(fn ($get) => \App\Models\Member::find($get('member_id'))?->email ?? '—'),

                                Placeholder::make('branch_disp')
                                    ->label('Assigned OIC Branch')
                                    ->content(fn ($get) => \App\Models\Member::find($get('member_id'))?->branchOffice?->name ?? '—'),
                            ]),
                            Grid::make(1)->schema([
                                Placeholder::make('address_disp')
                                    ->label('Complete Address')
                                    ->content(fn ($get) => \App\Models\Member::find($get('member_id'))?->address ?? '—'),
                            ]),
                        ]),
                ]),

            // SECTION 2: Loan Application Details
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
                            ->displayFormat('M d, Y')
                            ->live()
                            ->maxDate(now()->addYear()),
                        DatePicker::make('maturity_date')
                            ->label('Maturity Date')
                            ->native(false)
                            ->displayFormat('M d, Y')
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
                            ->live(),
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

                    Section::make('Comment / Conversation Thread')
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

                                    if ($notes->isEmpty()) {
                                        return new \Illuminate\Support\HtmlString('<div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400 italic">No comments in thread yet. Type a comment below and click Save to post a response.</div>');
                                    }

                                    $html = '<div class="space-y-3.5 max-h-[32rem] overflow-y-auto pr-1.5 py-1.5">';
                                    foreach ($notes as $n) {
                                        $date = $n->created_at ? $n->created_at->format('M d, Y • h:i A') : 'N/A';
                                        $timeAgo = $n->created_at ? $n->created_at->diffForHumans() : '';
                                        $author = e($n->user?->name ?? 'System');
                                        $role = e($n->user?->role?->name ?? 'Staff');
                                        $stage = e($n->stage ?? 'Comment');
                                        $noteContent = nl2br(e($n->note));

                                        $isBranch = str_contains($stage, 'Branch') || str_contains(strtolower($role), 'document specialist') || str_contains(strtolower($role), 'branch');

                                        $borderLeft = $isBranch ? 'border-l-4 border-amber-500' : 'border-l-4 border-blue-500';
                                        $badgeStyle = $isBranch ? 'bg-amber-100 dark:bg-amber-950/70 text-amber-800 dark:text-amber-300 border border-amber-300/60 dark:border-amber-800/50' : 'bg-blue-100 dark:bg-blue-950/70 text-blue-800 dark:text-blue-300 border border-blue-300/60 dark:border-blue-800/50';

                                        $html .= "
                                            <div class='p-4 rounded-xl bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 {$borderLeft} shadow-xs space-y-2.5'>
                                                <div class='flex items-center justify-between flex-wrap gap-2 text-xs'>
                                                    <div class='flex items-center flex-wrap gap-2'>
                                                        <span class='font-bold text-slate-900 dark:text-slate-100 text-sm'>👤 {$author}</span>
                                                        <span class='px-2 py-0.5 rounded-full text-[11px] font-semibold {$badgeStyle}'>{$role}</span>
                                                        <span class='px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300'>{$stage}</span>
                                                    </div>
                                                    <div class='text-[11px] text-slate-500 dark:text-slate-400 font-mono'>
                                                        ⏱️ {$date} <span class='text-slate-400 dark:text-slate-500'>({$timeAgo})</span>
                                                    </div>
                                                </div>
                                                <div class='text-sm text-slate-800 dark:text-slate-100 bg-white dark:bg-slate-950/80 p-3 rounded-lg border border-slate-200/80 dark:border-slate-800/80 leading-relaxed font-sans'>
                                                    {$noteContent}
                                                </div>
                                            </div>
                                        ";
                                    }
                                    $html .= '</div>';

                                    return new \Illuminate\Support\HtmlString($html);
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
                        ])
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
                                    ->required(),
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
                                    ->helperText('Month & Year only'),
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
                ->description('Uploaded scanned documentation files (.jpg, .png, .pdf)')
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
                    Action::make('openPhotoLightbox')
                        ->label('Photo Lightbox')
                        ->icon('heroicon-o-photo')
                        ->color('warning')
                        ->modalWidth('6xl')
                        ->modalHeading(fn ($record): string => "Photo Lightbox Gallery — " . ($record?->application_no ?? 'Loan Photos') . ($record?->member ? " ({$record->member->full_name})" : ''))
                        ->modalDescription('Full-screen interactive photo lightbox with zoom, 90° rotate, flip, and carousel slider')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close Lightbox')
                        ->modalContent(fn ($record) => view('filament.modals.photo-lightbox-modal', [
                            'record' => $record,
                        ]))
                        ->visible(function ($record) {
                            if (!$record) return false;
                            return $record->getMedia('*')->filter(function ($m) {
                                $mime = $m->mime_type ?? '';
                                $ext = strtolower(pathinfo($m->file_name, PATHINFO_EXTENSION));
                                return str_starts_with($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                            })->isNotEmpty();
                        }),
                ])
                ->schema([
                    \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('spatie_documents')
                        ->label('Upload Document Files (PNG, JPG, JPEG, PDF)')
                        ->collection('loan_application_documents')
                        ->preserveFilenames()
                        ->multiple()
                        ->reorderable()
                        ->openable()
                        ->downloadable()
                        ->panelLayout('grid')
                        ->imagePreviewHeight('180px')
                        ->columnSpanFull(),
                ]),

            // SECTION 5: Document Processing Tracker
            'document_tracking_section' => Section::make('📄 Document Processing Tracker')
                ->description('Track document movement through each government agency — Notary, BIR, ROD, LTO, Assessor\'s, and Encumbrance registration')
                ->icon('heroicon-o-clipboard-document-check')
                ->collapsible()
                ->visible(fn ($record) => $record && $record->approval_status === 'Approved')
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
                        ]),

                    // ── BIR ─────────────────────────────────────────────────
                    Section::make('Bureau of Internal Revenue (BIR)')
                        ->icon('heroicon-o-building-office')
                        ->compact()
                        ->collapsible()
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('bir_office')
                                    ->label('BIR Office / RDO')
                                    ->placeholder('e.g. RDO No. 98 — Cagayan de Oro'),
                                DatePicker::make('bir_payment_date')
                                    ->label('Date of Payment'),
                            ]),
                            Grid::make(2)->schema([
                                TextInput::make('bir_or_no')
                                    ->label('Official Receipt No. (OR No.)')
                                    ->placeholder('e.g. OR-2026-001234'),
                                TextInput::make('bir_amount_paid')
                                    ->label('Amount Paid (₱)')
                                    ->prefix('₱')
                                    ->placeholder('0.00')
                                    ->numeric(),
                            ]),
                        ]),

                    // ── ROD ─────────────────────────────────────────────────
                    Section::make('Registry of Deeds (ROD)')
                        ->icon('heroicon-o-building-library')
                        ->compact()
                        ->collapsible()
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('rod_entry_no')
                                    ->label('ROD Entry No.')
                                    ->placeholder('e.g. Entry No. 2026-0001234'),
                                DatePicker::make('rod_payment_date')
                                    ->label('Date Paid / Filed'),
                            ]),
                            Grid::make(3)->schema([
                                TextInput::make('rod_or_no')
                                    ->label('Official Receipt No. (OR No.)')
                                    ->placeholder('e.g. OR-2026-005678'),
                                TextInput::make('rod_amount_paid')
                                    ->label('Amount Paid (₱)')
                                    ->prefix('₱')
                                    ->placeholder('0.00')
                                    ->numeric(),
                                DatePicker::make('rod_release_date')
                                    ->label('Date Released / Returned'),
                            ]),
                        ]),

                    // ── LTO (CM only) ────────────────────────────────────────
                    Section::make('Land Transportation Office (LTO)')
                        ->icon('heroicon-o-truck')
                        ->compact()
                        ->collapsible()
                        ->visible(fn ($get) => in_array($get('collateral_type'), ['chattel_mortgage', 'cm_certification', 'cm_amendment']))
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('lto_office')
                                    ->label('LTO Office')
                                    ->placeholder('e.g. LTO CDO Extension Office'),
                                DatePicker::make('lto_payment_date')
                                    ->label('Date Paid'),
                            ]),
                            Grid::make(3)->schema([
                                TextInput::make('lto_or_no')
                                    ->label('Official Receipt No. (OR No.)')
                                    ->placeholder('e.g. OR-2026-009876'),
                                TextInput::make('lto_amount_paid')
                                    ->label('Amount Paid (₱)')
                                    ->prefix('₱')
                                    ->placeholder('0.00')
                                    ->numeric(),
                                DatePicker::make('lto_release_date')
                                    ->label('Date Released / Returned'),
                            ]),
                        ]),

                    // ── ASSESSOR'S OFFICE ─────────────────────────────────────
                    Section::make("Assessor's Office")
                        ->icon('heroicon-o-map')
                        ->compact()
                        ->collapsible()
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

                    // ── ENCUMBERED ────────────────────────────────────────────
                    Section::make('Encumbrance Registration')
                        ->icon('heroicon-o-lock-closed')
                        ->compact()
                        ->collapsible()
                        ->schema([
                            Grid::make(2)->schema([
                                DatePicker::make('encumbered_date')
                                    ->label('Date Encumbered / Annotated'),
                                TextInput::make('annotation_no')
                                    ->label('Annotation / Entry No.')
                                    ->placeholder('e.g. 2026-ANN-00123'),
                            ]),
                        ]),
                ]),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        $sections = static::getFormSections();

        return $schema
            ->components([
                // CREATE OPERATION: Step-by-step 4-Step Wizard
                Wizard::make([
                    Step::make('Members Info')
                        ->icon('heroicon-o-user')
                        ->schema([$sections['member_section']]),

                    Step::make('Loan Application')
                        ->icon('heroicon-o-banknotes')
                        ->schema([$sections['loan_details_section']]),

                    Step::make('Collateral Information')
                        ->icon('heroicon-o-home')
                        ->schema([$sections['collateral_section']]),

                    Step::make('Scan / Upload')
                        ->icon('heroicon-o-document-arrow-up')
                        ->schema([$sections['document_section']]),
                ])
                ->visible(fn (string $operation) => $operation === 'create')
                ->columnSpanFull(),

                // EDIT / VIEW OPERATION: Merged Member & Loan Application Tab + Collateral Info + Scan/Upload Tabs
                Tabs::make('Loan Application Details')
                    ->tabs([
                        Tab::make('Member & Loan Application')
                            ->icon('heroicon-o-user')
                            ->schema([
                                $sections['member_section'],
                                $sections['loan_details_section'],
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
                    ->visible(fn (string $operation) => $operation !== 'create')
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
                        ->displayFormat('M d, Y')
                        ->default(fn (LoanApplication $record) => $record->date_granted ?? now())
                        ->live()
                        ->maxDate(now()->addYear())
                        ->required(),
                    DatePicker::make('maturity_date')
                        ->label('Maturity Date')
                        ->native(false)
                        ->displayFormat('M d, Y')
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
            ->action(function (LoanApplication $record) {
                $memberNo = $record->member?->member_no ?? 'MEMBER-GENERAL';
                $appNo = $record->application_no ?? 'LN-GENERAL';
                $password = $memberNo;

                $safeMemberNo = str_replace(['/', '\\'], '-', $memberNo);
                $safeAppNo = str_replace(['/', '\\'], '-', $appNo);

                $basePath = storage_path("app/private/{$safeMemberNo}/{$safeAppNo}");

                if (!file_exists($basePath)) {
                    \Filament\Notifications\Notification::make()
                        ->title('No Files Found')
                        ->body("No document files found for application {$appNo}.")
                        ->warning()
                        ->send();
                    return;
                }

                $tempZipPath = storage_path("app/private/temp_{$safeAppNo}_" . time() . ".zip");
                $zip = new ZipArchive();

                if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    \Filament\Notifications\Notification::make()
                        ->title('Archive Error')
                        ->body('Unable to generate zip archive file.')
                        ->danger()
                        ->send();
                    return;
                }

                $zip->setPassword($password);

                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                $folderName = $record->member?->full_name
                    ? str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $record->member->full_name)
                    : $safeAppNo;

                $fileCount = 0;
                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($basePath) + 1);
                        $zipEntryName = "{$folderName}/" . str_replace('\\', '/', $relativePath);

                        $zip->addFile($filePath, $zipEntryName);
                        if (defined('ZipArchive::EM_AES_256')) {
                            $zip->setEncryptionName($zipEntryName, ZipArchive::EM_AES_256);
                        }
                        $fileCount++;
                    }
                }

                $zip->close();

                if ($fileCount === 0) {
                    if (file_exists($tempZipPath)) {
                        unlink($tempZipPath);
                    }
                    \Filament\Notifications\Notification::make()
                        ->title('No Files to Download')
                        ->body("No files are currently stored in application {$appNo}.")
                        ->warning()
                        ->send();
                    return;
                }

                $downloadFileName = $record->member?->full_name ? str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $record->member->full_name) : $safeAppNo;

                return response()->download($tempZipPath, "{$downloadFileName}.zip")->deleteFileAfterSend(true);
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date Filed')
                    ->dateTime('M d, Y')
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
                    ->formatStateUsing(fn ($record) => $record->member?->full_name ?? '—')
                    ->description(fn ($record) => $record->branchOffice?->name)
                    ->copyable()
                    ->copyableState(fn ($record) => $record->member?->full_name)
                    ->copyMessage('Borrower name copied to clipboard!')
                    ->copyMessageDuration(1500)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('requested_amount')
                    ->label('Requested Amount')
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

                        return new \Illuminate\Support\HtmlString("<span style='font-size: 10px; font-weight: 500; opacity: 0.85; display: block; margin-top: 2px;'>{$countText} • {$colStatus}</span>");
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
                        $dbStatus = \App\Models\ApplicationStatus::where('name', $state)->first();
                        return $dbStatus?->color ?? 'info';
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
                Tables\Columns\TextColumn::make('loan_account_no')
                    ->label('Loan Account No.')
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('Loan Account No. copied to clipboard!')
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
                        'real_estate_mortgage' => 'Real Estate Mortgage',
                        'chattel_mortgage' => 'Chattel Mortgage',
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
                    static::getApproveTableAction(),
                    static::getDisapproveTableAction(),
                    static::getDownloadArchiveTableAction(),
                    EditAction::make()
                        ->url(fn (LoanApplication $record): string => LoanApplicationResource::getUrl('edit', ['record' => $record])),
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
            'index' => Pages\ListLoanApplications::route('/'),
            'create' => Pages\CreateLoanApplication::route('/create'),
            'view' => Pages\ViewLoanApplication::route('/{record}'),
            'edit' => Pages\EditLoanApplication::route('/{record}/edit'),
        ];
    }
}
