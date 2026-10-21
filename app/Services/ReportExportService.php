<?php

namespace App\Services;

use App\Models\BranchOffice;
use App\Models\ChattelCollateral;
use App\Models\LoanApplication;
use App\Models\RealEstateCollateral;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    /**
     * Resolve date range from preset or custom inputs.
     * Returns [?string $startDate, ?string $endDate, string $presetLabel]
     */
    public static function resolveDateRange(array $filters): array
    {
        $preset = $filters['date_preset'] ?? 'all';
        $startDate = $filters['startDate'] ?? null;
        $endDate = $filters['endDate'] ?? null;

        $range = match ($preset) {
            'this_month' => [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
                'This Month (' . now()->format('F Y') . ')',
            ],
            'last_month' => [
                now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
                'Last Month (' . now()->subMonthNoOverflow()->format('F Y') . ')',
            ],
            'midyear' => [
                now()->startOfYear()->toDateString(),
                now()->startOfYear()->addMonths(5)->endOfMonth()->toDateString(),
                'Midyear (' . now()->startOfYear()->format('F d') . ' - ' . now()->startOfYear()->addMonths(5)->endOfMonth()->format('F d, Y') . ')',
            ],
            'this_year' => [
                now()->startOfYear()->toDateString(),
                now()->endOfYear()->toDateString(),
                'This Year (' . now()->year . ')',
            ],
            'last_year' => [
                now()->subYear()->startOfYear()->toDateString(),
                now()->subYear()->endOfYear()->toDateString(),
                'Last Year (' . (now()->year - 1) . ')',
            ],
            'custom' => [
                $startDate,
                $endDate,
                ($startDate && $endDate) ? "{$startDate} to {$endDate}" : 'Custom Range',
            ],
            default => [null, null, 'All Record'],
        };

        return $range;
    }

    /**
     * Get report data bundle for preview, print, or excel.
     */
    public static function getReportData(string $reportType, array $filters, $user = null): array
    {
        $user = $user ?? auth()->user();
        $allowed = $user?->getAllowedBranchIds();

        [$startDate, $endDate, $dateLabel] = self::resolveDateRange($filters);
        $selectedBranch = $filters['branch_office_id'] ?? null;
        $selectedCollateral = $filters['collateral_type'] ?? null;

        $branchName = 'All Allowed Branches';
        if ($selectedBranch) {
            $branchName = BranchOffice::find($selectedBranch)?->name ?? "Branch #{$selectedBranch}";
        }

        $selectedDeficiency = $filters['deficiency_type'] ?? null;

        $metadata = [
            'generated_at'    => now()->format('F d, Y h:i A'),
            'generated_by'    => $user?->name ?? 'System User',
            'date_label'      => $dateLabel,
            'branch_label'    => $branchName,
            'collateral_label'=> match ($selectedCollateral) {
                'real_estate_mortgage' => 'Real Estate Mortgage (REM)',
                'chattel_mortgage'     => 'Chattel Mortgage (CM)',
                default                => 'All Collateral Types',
            },
            'deficiency_label'=> match ($selectedDeficiency) {
                'internal' => 'Internal Deficiency Only',
                'external' => 'External Deficiency Only',
                default    => null,
            },
            'approval_label'  => match ($filters['approval_status'] ?? 'all') {
                'Approved'            => 'Approved Only',
                'Disapproved_Pending' => 'Disapproved & Pending',
                'Disapproved'         => 'Disapproved Only',
                'Pending'             => 'Pending Only',
                default               => 'All Statuses',
            },
        ];

        return match ($reportType) {
            'branch_consolidated'  => self::getBranchConsolidatedReport($startDate, $endDate, $selectedBranch, $selectedCollateral, $allowed, $metadata),
            'loan_release'         => self::getLoanReleaseReport($startDate, $endDate, $selectedBranch, $selectedCollateral, $allowed, $metadata),
            'pipeline_aging'       => self::getPipelineAgingReport($startDate, $endDate, $selectedBranch, $selectedCollateral, $allowed, $metadata),
            'collateral_inventory' => self::getCollateralInventoryReport($startDate, $endDate, $selectedBranch, $selectedCollateral, $allowed, $metadata),
            'lto_renewal'          => self::getLtoRenewalReport($startDate, $endDate, $selectedBranch, $filters['approval_status'] ?? 'all', $allowed, $metadata, $filters['date_preset'] ?? 'this_month'),
            'deficiency_audit'     => self::getDeficiencyAuditReport($startDate, $endDate, $selectedBranch, $selectedCollateral, $selectedDeficiency, $allowed, $metadata),
            'rod_pending_status', 'epeb_status' => self::getRodPendingStatusReport($filters, $allowed, $metadata),
            default                => self::getBranchConsolidatedReport($startDate, $endDate, $selectedBranch, $selectedCollateral, $allowed, $metadata),
        };
    }

    /**
     * 1. Loan Release & Portfolio Summary Report
     */
    protected static function getLoanReleaseReport(?string $startDate, ?string $endDate, ?string $branchId, ?string $collateralType, ?array $allowed, array $metadata): array
    {
        $query = LoanApplication::query()
            ->with(['member', 'branchOffice'])
            ->where('approval_status', 'Approved');

        if (is_array($allowed)) {
            $query->whereIn('branch_office_id', $allowed);
        }
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        if ($branchId) {
            $query->where('branch_office_id', $branchId);
        }
        if ($collateralType) {
            if ($collateralType === 'real_estate_mortgage') {
                $query->where(fn ($q) => $q->where('collateral_type', 'like', '%real%')->orWhere('collateral_type', 'like', '%rem%'));
            } elseif ($collateralType === 'chattel_mortgage') {
                $query->where(fn ($q) => $q->where('collateral_type', 'like', '%chattel%')->orWhere('collateral_type', 'like', '%cm%'));
            }
        }

        $records = $query->orderByDesc('created_at')->get();

        $totalCount = $records->count();
        $totalVolume = $records->sum('requested_amount');
        $avgAmount = $totalCount > 0 ? $totalVolume / $totalCount : 0;
        $remCount = $records->filter(fn ($r) => str_contains(strtolower((string) $r->collateral_type), 'real') || str_contains(strtolower((string) $r->collateral_type), 'rem'))->count();
        $cmCount = $records->filter(fn ($r) => str_contains(strtolower((string) $r->collateral_type), 'chattel') || str_contains(strtolower((string) $r->collateral_type), 'cm'))->count();

        // Operational Pipeline & Compliance counts
        $encumberedCount = $records->filter(fn ($r) => $r->isEncumbered())->count();
        $onProcessCount = $records->filter(fn ($r) => !$r->isEncumbered())->count();
        $withDeficiencyCount = $records->filter(fn ($r) => !empty($r->sub_status) && $r->sub_status !== 'none')->count();

        $kpis = [
            ['label' => 'Total Released Loans', 'value' => number_format($totalCount), 'icon' => 'heroicon-m-document-check'],
            ['label' => 'Total Portfolio Volume', 'value' => '₱' . number_format($totalVolume, 2), 'icon' => 'heroicon-m-banknotes'],
            ['label' => 'On-Process Pipeline', 'value' => number_format($onProcessCount), 'icon' => 'heroicon-m-arrow-path'],
            ['label' => 'Fully Encumbered', 'value' => number_format($encumberedCount), 'icon' => 'heroicon-m-shield-check'],
            ['label' => 'With Deficiency', 'value' => number_format($withDeficiencyCount), 'icon' => 'heroicon-m-exclamation-triangle'],
            ['label' => 'Collateral Breakdown', 'value' => "{$remCount} REM • {$cmCount} CM", 'icon' => 'heroicon-m-scale'],
        ];

        $headers = [
            '#',
            'Date Filed',
            'Application ID',
            'Borrower Name',
            'Member No.',
            'OIC Branch',
            'Collateral Type',
            'Loan Amount (₱)',
            'Date Granted',
            'Maturity Date',
            'Pipeline Status',
            'Deficiency Status',
            'Assessment Notes / Remarks',
        ];

        $rows = [];
        foreach ($records as $index => $r) {
            $memberName = $r->member ? "{$r->member->last_name}, {$r->member->first_name}" : 'N/A';
            $deficiencyText = !empty($r->sub_status) && $r->sub_status !== 'none'
                ? strtoupper(str_replace('_', ' ', (string) $r->sub_status))
                : 'CLEAN';

            $rows[] = [
                $index + 1,
                $r->created_at ? $r->created_at->format('Y-m-d') : 'N/A',
                $r->application_no ?? ('APP-' . $r->id),
                $memberName,
                $r->member?->member_no ?? 'N/A',
                $r->branchOffice?->name ?? 'No Branch',
                strtoupper(str_replace('_', ' ', (string) $r->collateral_type)),
                (float) $r->requested_amount,
                $r->date_granted ? Carbon::parse($r->date_granted)->format('Y-m-d') : 'Pending',
                $r->maturity_date ? Carbon::parse($r->maturity_date)->format('Y-m-d') : 'Pending',
                $r->status ?? 'Approved',
                $deficiencyText,
                $r->notes ?: '—',
            ];
        }

        return [
            'type'        => 'loan_release',
            'title'       => 'Loan Release & Portfolio Summary Report',
            'description' => 'Comprehensive breakdown of approved & granted loans within the selected period',
            'kpis'        => $kpis,
            'headers'     => $headers,
            'rows'        => $rows,
            'metadata'    => $metadata,
            'total_volume'=> $totalVolume,
        ];
    }

    /**
     * 2. Document Pipeline & Aging Turnaround Report
     */
    protected static function getPipelineAgingReport(?string $startDate, ?string $endDate, ?string $branchId, ?string $collateralType, ?array $allowed, array $metadata): array
    {
        $query = LoanApplication::query()
            ->with(['member', 'branchOffice'])
            ->onProcess();

        if (is_array($allowed)) {
            $query->whereIn('branch_office_id', $allowed);
        }
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        if ($branchId) {
            $query->where('branch_office_id', $branchId);
        }
        if ($collateralType) {
            if ($collateralType === 'real_estate_mortgage') {
                $query->where(fn ($q) => $q->where('collateral_type', 'like', '%real%')->orWhere('collateral_type', 'like', '%rem%'));
            } elseif ($collateralType === 'chattel_mortgage') {
                $query->where(fn ($q) => $q->where('collateral_type', 'like', '%chattel%')->orWhere('collateral_type', 'like', '%cm%'));
            }
        }

        $records = $query->orderByDesc('created_at')->get();

        $totalCount = $records->count();
        $totalVolume = $records->sum('requested_amount');
        $delayedCount = $records->filter(function ($r) {
            $date = $r->status_changed_at ?? $r->updated_at ?? $r->created_at;
            return $date ? Carbon::parse($date)->diffInDays(now()) > 30 : false;
        })->count();

        $kpis = [
            ['label' => 'Active in Pipeline', 'value' => number_format($totalCount), 'icon' => 'heroicon-m-arrow-path'],
            ['label' => 'Active Loan Value', 'value' => '₱' . number_format($totalVolume, 2), 'icon' => 'heroicon-m-banknotes'],
            ['label' => 'Delayed (>30 Days in Stage)', 'value' => number_format($delayedCount), 'icon' => 'heroicon-m-clock'],
            ['label' => 'Critical (>60 Days)', 'value' => number_format($records->filter(fn ($r) => ($r->status_changed_at ? Carbon::parse($r->status_changed_at)->diffInDays(now()) : 0) > 60)->count()), 'icon' => 'heroicon-m-exclamation-triangle'],
        ];

        $headers = [
            '#',
            'Application ID',
            'Borrower Name',
            'OIC Branch',
            'Collateral',
            'Loan Amount (₱)',
            'Current Stage',
            'Stage Date',
            'Days in Stage',
            'Aging Severity',
            'Deficiency Status',
            'Assessment Notes / Remarks',
        ];

        $rows = [];
        foreach ($records as $index => $r) {
            $memberName = $r->member ? "{$r->member->last_name}, {$r->member->first_name}" : 'N/A';
            $stageDate = $r->status_changed_at ?? $r->updated_at ?? $r->created_at;
            $daysAging = $stageDate ? (int) Carbon::parse($stageDate)->diffInDays(now()) : 0;
            $severity = $daysAging > 60 ? 'CRITICAL' : ($daysAging > 30 ? 'DELAYED' : 'ON TRACK');
            $deficiency = $r->sub_status ? strtoupper(str_replace('_', ' ', $r->sub_status)) : 'None';

            $rows[] = [
                $index + 1,
                $r->application_no ?? ('APP-' . $r->id),
                $memberName,
                $r->branchOffice?->name ?? 'No Branch',
                strtoupper(str_replace('_', ' ', (string) $r->collateral_type)),
                (float) $r->requested_amount,
                $r->status ?? 'Active',
                $stageDate ? Carbon::parse($stageDate)->format('Y-m-d') : 'N/A',
                $daysAging,
                $severity,
                $deficiency,
                $r->notes ?: '—',
            ];
        }

        return [
            'type'        => 'pipeline_aging',
            'title'       => 'Document Pipeline & Aging Turnaround Report',
            'description' => 'Monitoring of active loans in documentation pipeline and aging turnaround bottlenecks',
            'kpis'        => $kpis,
            'headers'     => $headers,
            'rows'        => $rows,
            'metadata'    => $metadata,
            'total_volume'=> $totalVolume,
        ];
    }

    /**
     * 3. Collateral & Encumbrance Inventory Report
     */
    protected static function getCollateralInventoryReport(?string $startDate, ?string $endDate, ?string $branchId, ?string $collateralType, ?array $allowed, array $metadata): array
    {
        $query = LoanApplication::query()
            ->with(['member', 'branchOffice', 'chattelCollaterals', 'realEstateCollaterals'])
            ->where('approval_status', 'Approved');

        if (is_array($allowed)) {
            $query->whereIn('branch_office_id', $allowed);
        }
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        if ($branchId) {
            $query->where('branch_office_id', $branchId);
        }
        if ($collateralType) {
            if ($collateralType === 'real_estate_mortgage') {
                $query->where(fn ($q) => $q->where('collateral_type', 'like', '%real%')->orWhere('collateral_type', 'like', '%rem%'));
            } elseif ($collateralType === 'chattel_mortgage') {
                $query->where(fn ($q) => $q->where('collateral_type', 'like', '%chattel%')->orWhere('collateral_type', 'like', '%cm%'));
            }
        }

        $records = $query->orderByDesc('created_at')->get();

        $totalLoans = $records->count();
        $encumberedCount = $records->filter(fn ($r) => $r->isEncumbered())->count();
        $onProcessCount = $totalLoans - $encumberedCount;

        $kpis = [
            ['label' => 'Total Collateral Accounts', 'value' => number_format($totalLoans), 'icon' => 'heroicon-m-folder'],
            ['label' => 'Fully Encumbered', 'value' => number_format($encumberedCount), 'icon' => 'heroicon-m-shield-check'],
            ['label' => 'On-Process Collaterals', 'value' => number_format($onProcessCount), 'icon' => 'heroicon-m-arrow-path'],
            ['label' => 'Total Loan Exposure', 'value' => '₱' . number_format($records->sum('requested_amount'), 2), 'icon' => 'heroicon-m-banknotes'],
        ];

        $headers = [
            '#',
            'App ID',
            'Borrower Name',
            'Branch',
            'Type',
            'Loan Amount (₱)',
            'Collateral Identification',
            'Registration / Title Details',
            'Encumbrance Status',
            'Pipeline Status',
        ];

        $rows = [];
        $i = 1;
        foreach ($records as $r) {
            $memberName = $r->member ? "{$r->member->last_name}, {$r->member->first_name}" : 'N/A';
            $isRem = str_contains(strtolower((string) $r->collateral_type), 'real') || str_contains(strtolower((string) $r->collateral_type), 'rem');

            $ident = 'N/A';
            $regDetails = 'N/A';
            $encStatus = $r->isEncumbered() ? 'SECURED' : 'ON-PROCESS';

            if ($isRem && $r->realEstateCollaterals->isNotEmpty()) {
                $rem = $r->realEstateCollaterals->first();
                $ident = "Title: " . ($rem->title_number ?: 'N/A');
                $regDetails = "Loc: " . ($rem->location ?: 'N/A') . " • TaxDec: " . ($rem->tax_dec_land ?: 'N/A');
            } elseif ($r->chattelCollaterals->isNotEmpty()) {
                $cm = $r->chattelCollaterals->first();
                $ident = "Plate: " . ($cm->plate_no ?: ($cm->mv_file_no ?: 'N/A'));
                $regDetails = "Model: " . ($cm->year_model ?: '') . " " . ($cm->vehicle_description ?: 'Vehicle') . " • Eng: " . ($cm->engine_no ?: 'N/A');
            }

            $rows[] = [
                $i++,
                $r->application_no ?? ('APP-' . $r->id),
                $memberName,
                $r->branchOffice?->name ?? 'No Branch',
                $isRem ? 'REM' : 'CM',
                (float) $r->requested_amount,
                $ident,
                $regDetails,
                $encStatus,
                $r->status ?? 'Approved',
            ];
        }

        return [
            'type'        => 'collateral_inventory',
            'title'       => 'Collateral & Encumbrance Inventory Report',
            'description' => 'Master inventory of Real Estate and Chattel collaterals with encumbrance security status',
            'kpis'        => $kpis,
            'headers'     => $headers,
            'rows'        => $rows,
            'metadata'    => $metadata,
            'total_volume'=> $records->sum('requested_amount'),
        ];
    }

    /**
     * 4. LTO Vehicle Registration & Renewal Due Report
     */
    protected static function getLtoRenewalReport(?string $startDate, ?string $endDate, ?string $branchId, ?string $approvalStatus, ?array $allowed, array $metadata, string $datePreset = 'this_month'): array
    {
        $query = ChattelCollateral::query()
            ->with(['loanApplication.member', 'loanApplication.branchOffice'])
            ->orderByRaw('CASE WHEN registration_renewal_date < NOW() THEN 0 ELSE 1 END')
            ->orderBy('registration_renewal_date', 'asc');

        if (is_array($allowed)) {
            $query->whereHas('loanApplication', fn ($q) => $q->whereIn('branch_office_id', $allowed));
        }
        if ($branchId) {
            $query->whereHas('loanApplication', fn ($q) => $q->where('branch_office_id', $branchId));
        }
        if ($approvalStatus === 'Approved') {
            $query->whereHas('loanApplication', fn ($q) => $q->where('approval_status', 'Approved'));
        } elseif ($approvalStatus === 'Disapproved_Pending') {
            $query->whereHas('loanApplication', fn ($q) => $q->where('approval_status', '!=', 'Approved')->orWhereNull('approval_status'));
        } elseif ($approvalStatus === 'Disapproved') {
            $query->whereHas('loanApplication', fn ($q) => $q->where('approval_status', 'Disapproved'));
        } elseif ($approvalStatus === 'Pending') {
            $query->whereHas('loanApplication', fn ($q) => $q->whereIn('approval_status', ['Pending', 'Pending Approval'])->orWhereNull('approval_status'));
        }

        if ($datePreset === 'this_month' && $endDate) {
            $query->whereDate('registration_renewal_date', '<=', $endDate);
        } elseif ($startDate && $endDate) {
            $query->whereBetween('registration_renewal_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->whereDate('registration_renewal_date', '>=', $startDate);
        } elseif ($endDate) {
            $query->whereDate('registration_renewal_date', '<=', $endDate);
        }

        $records = $query->get();

        $totalVehicles = $records->count();
        $expiredCount = $records->filter(fn ($v) => $v->registration_renewal_date && Carbon::parse($v->registration_renewal_date)->isPast())->count();
        $dueSoonCount = $records->filter(function ($v) {
            if (!$v->registration_renewal_date) return false;
            $c = Carbon::parse($v->registration_renewal_date);
            return !$c->isPast() && $c->diffInDays(now()) <= 30;
        })->count();

        $totalLoanVolume = (float) $records->sum(fn ($v) => $v->loanApplication?->requested_amount ?? 0);

        $kpis = [
            ['label' => 'Total Vehicle Collaterals', 'value' => number_format($totalVehicles), 'icon' => 'heroicon-m-truck'],
            ['label' => 'Total Loan Amount', 'value' => '₱' . number_format($totalLoanVolume, 2), 'icon' => 'heroicon-m-banknotes'],
            ['label' => 'Expired LTO Registration', 'value' => number_format($expiredCount), 'icon' => 'heroicon-m-exclamation-triangle'],
            ['label' => 'Due in 30 Days', 'value' => number_format($dueSoonCount), 'icon' => 'heroicon-m-clock'],
            ['label' => 'Active / Updated', 'value' => number_format(max(0, $totalVehicles - $expiredCount - $dueSoonCount)), 'icon' => 'heroicon-m-check-badge'],
        ];

        $headers = [
            '#',
            'Date Filed',
            'Borrower Name',
            'Branch Office',
            'Loan Amount (₱)',
            'Owner Name',
            'Plate / MV File No.',
            'Vehicle Info',
            'Engine / Chassis No.',
            'Approval Status',
            'LTO Renewal Date',
            'Days Left / Overdue',
            'LTO Status',
        ];

        $rows = [];
        foreach ($records as $index => $v) {
            $member = $v->loanApplication?->member;
            $memberName = $member?->natural_full_name ?? ($member ? "{$member->first_name} {$member->last_name}" : 'N/A');
            $approval = $v->loanApplication?->approval_status ?? 'Pending';
            $loanAmount = (float) ($v->loanApplication?->requested_amount ?? 0);
            $date = $v->registration_renewal_date;

            $status = 'No Date';
            $daysLeft = 'N/A';

            if ($date) {
                $c = Carbon::parse($date);
                if ($c->isPast()) {
                    $d = abs((int) now()->diffInDays($c));
                    $status = 'EXPIRED';
                    $daysLeft = "Expired {$d}d ago";
                } else {
                    $d = (int) now()->diffInDays($c);
                    $status = $d <= 30 ? 'DUE SOON' : 'ACTIVE';
                    $daysLeft = "{$d} days left";
                }
            }

            $rows[] = [
                $index + 1,
                $v->loanApplication?->created_at ? $v->loanApplication->created_at->format('F d, Y') : 'N/A',
                $memberName,
                $v->loanApplication?->branchOffice?->name ?? 'No Branch',
                $loanAmount,
                $v->owner_name ?? '—',
                $v->plate_no ?: ($v->mv_file_no ?: 'No Plate'),
                ($v->year_model ? "{$v->year_model} " : '') . ($v->vehicle_description ?: 'Vehicle'),
                ($v->engine_no ?: 'N/A') . ' / ' . ($v->chassis_no ?: 'N/A'),
                $approval,
                $date ? Carbon::parse($date)->format('F d, Y') : 'None',
                $daysLeft,
                $status,
            ];
        }

        $totalLoanVolume = (float) $records->sum(fn ($v) => $v->loanApplication?->requested_amount ?? 0);

        return [
            'type'        => 'lto_renewal',
            'title'       => 'Vehicle LTO Registration & Renewal Due Report',
            'description' => 'Master schedule of chattel mortgage vehicles with upcoming or expired LTO registration',
            'kpis'        => $kpis,
            'headers'     => $headers,
            'rows'        => $rows,
            'metadata'    => $metadata,
            'total_volume'=> $totalLoanVolume,
        ];
    }

    /**
     * 5. Deficiency & Compliance Audit Report
     */
    protected static function getDeficiencyAuditReport(?string $startDate, ?string $endDate, ?string $branchId, ?string $collateralType, ?string $deficiencyType, ?array $allowed, array $metadata): array
    {
        $baseQuery = LoanApplication::query()
            ->with(['member', 'branchOffice'])
            ->where('approval_status', 'Approved')
            ->whereIn('sub_status', ['internal_deficiency', 'external_deficiency']);

        if (is_array($allowed)) {
            $baseQuery->whereIn('branch_office_id', $allowed);
        }
        if ($startDate) {
            $baseQuery->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $baseQuery->whereDate('created_at', '<=', $endDate);
        }
        if ($branchId) {
            $baseQuery->where('branch_office_id', $branchId);
        }
        if ($collateralType) {
            if ($collateralType === 'real_estate_mortgage') {
                $baseQuery->where(fn ($q) => $q->where('collateral_type', 'like', '%real%')->orWhere('collateral_type', 'like', '%rem%'));
            } elseif ($collateralType === 'chattel_mortgage') {
                $baseQuery->where(fn ($q) => $q->where('collateral_type', 'like', '%chattel%')->orWhere('collateral_type', 'like', '%cm%'));
            }
        }

        $allScopeRecords = $baseQuery->get();

        $totalDeficiencies = $allScopeRecords->count();
        $internalCount = $allScopeRecords->where('sub_status', 'internal_deficiency')->count();
        $externalCount = $allScopeRecords->where('sub_status', 'external_deficiency')->count();
        $totalExposedVolume = (float) $allScopeRecords->sum('requested_amount');

        $kpis = [
            ['label' => 'Total Deficiencies', 'value' => number_format($totalDeficiencies), 'icon' => 'heroicon-m-exclamation-circle'],
            ['label' => 'Internal Deficiencies', 'value' => number_format($internalCount), 'icon' => 'heroicon-m-building-office'],
            ['label' => 'External Deficiencies', 'value' => number_format($externalCount), 'icon' => 'heroicon-m-globe-americas'],
            ['label' => 'Total Exposed Amount', 'value' => '₱' . number_format($totalExposedVolume, 2), 'icon' => 'heroicon-m-banknotes'],
        ];

        if ($deficiencyType === 'internal') {
            $records = $allScopeRecords->where('sub_status', 'internal_deficiency');
        } elseif ($deficiencyType === 'external') {
            $records = $allScopeRecords->where('sub_status', 'external_deficiency');
        } else {
            $records = $allScopeRecords;
        }

        $records = $records->sortByDesc('updated_at');

        $headers = [
            '#',
            'Date Filed',
            'Loan Account',
            'Borrower Name',
            'Branch Office',
            'Collateral',
            'Loan Amount (₱)',
            'Deficiency Classification',
            'Pipeline Stage',
            'Assessment Notes / Remarks (Head Office Assessment)',
            'Last Updated',
        ];

        $rows = [];
        foreach ($records->values() as $index => $r) {
            $memberName = $r->member?->natural_full_name ?? ($r->member ? "{$r->member->first_name} {$r->member->last_name}" : 'N/A');
            $classification = match ($r->sub_status) {
                'internal_deficiency' => 'INTERNAL DEFICIENCY',
                'external_deficiency' => 'EXTERNAL DEFICIENCY',
                default               => strtoupper(str_replace('_', ' ', (string) ($r->sub_status ?: 'DEFICIENCY'))),
            };
            $collateral = str_contains(strtolower((string) $r->collateral_type), 'real') || str_contains(strtolower((string) $r->collateral_type), 'rem') ? 'REM' : (str_contains(strtolower((string) $r->collateral_type), 'chattel') || str_contains(strtolower((string) $r->collateral_type), 'cm') ? 'CM' : strtoupper((string) $r->collateral_type));

            $notes = $r->notes ?: 'Deficiency noted for compliance';

            $rows[] = [
                $index + 1,
                $r->created_at ? $r->created_at->format('F d, Y') : 'N/A',
                $r->loan_account_no ?? '—',
                $memberName,
                $r->branchOffice?->name ?? 'No Branch',
                $collateral,
                (float) $r->requested_amount,
                $classification,
                $r->status ?? 'N/A',
                $notes,
                $r->updated_at ? $r->updated_at->format('F d, Y') : 'N/A',
            ];
        }

        return [
            'type'        => 'deficiency_audit',
            'title'       => 'Deficiency & Compliance Audit Report',
            'description' => 'Audit log of loan accounts with internal or external document deficiencies',
            'kpis'        => $kpis,
            'headers'     => $headers,
            'rows'        => $rows,
            'metadata'    => $metadata,
            'total_volume'=> $totalExposedVolume,
        ];
    }

    /**
     * 6. Consolidated Branch Report (Performance, Encumbrance, Pipeline & Deficiency Matrix)
     */
    protected static function getBranchConsolidatedReport(?string $startDate, ?string $endDate, ?string $branchId, ?string $collateralType, ?array $allowed, array $metadata): array
    {
        // 1. Fetch active branches within scope
        $branchQuery = BranchOffice::query()->where('is_active', true)->orderBy('name');
        if (is_array($allowed)) {
            $branchQuery->whereIn('id', $allowed);
        }
        if ($branchId) {
            $branchQuery->where('id', $branchId);
        }
        $branches = $branchQuery->get();

        // 2. Fetch loan applications matching filters
        $loanQuery = LoanApplication::query()
            ->where('approval_status', 'Approved');

        if (is_array($allowed)) {
            $loanQuery->whereIn('branch_office_id', $allowed);
        }
        if ($startDate) {
            $loanQuery->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $loanQuery->whereDate('created_at', '<=', $endDate);
        }
        if ($branchId) {
            $loanQuery->where('branch_office_id', $branchId);
        }
        if ($collateralType) {
            if ($collateralType === 'real_estate_mortgage') {
                $loanQuery->where(fn ($q) => $q->where('collateral_type', 'like', '%real%')->orWhere('collateral_type', 'like', '%rem%'));
            } elseif ($collateralType === 'chattel_mortgage') {
                $loanQuery->where(fn ($q) => $q->where('collateral_type', 'like', '%chattel%')->orWhere('collateral_type', 'like', '%cm%'));
            }
        }

        $loans = $loanQuery->get();
        $loansByBranch = $loans->groupBy('branch_office_id');

        $headers = [
            'OIC Branch',
            'Chattel Mortgage (CM)',
            'Real Estate Mortgage (REM)',
            'Total Approved Amount (₱)',
            'ENCUMBERED CM',
            'ENCUMBERED REM',
            'STILL ON PROCESS CM',
            'STILL ON PROCESS REM',
            'Deficiency CM',
            'Deficiency REM',
            'Encumbrance Rate %',
        ];

        $rows = [];
        $totalCm = 0;
        $totalRem = 0;
        $totalApprovedVolume = 0;
        $totalEncCm = 0;
        $totalEncRem = 0;
        $totalOpCm = 0;
        $totalOpRem = 0;
        $totalDefCm = 0;
        $totalDefRem = 0;

        foreach ($branches as $branch) {
            $bLoans = $loansByBranch->get($branch->id, collect());

            $cmLoans = $bLoans->filter(fn ($l) => str_contains(strtolower((string) $l->collateral_type), 'chattel') || str_contains(strtolower((string) $l->collateral_type), 'cm'));
            $remLoans = $bLoans->filter(fn ($l) => str_contains(strtolower((string) $l->collateral_type), 'real') || str_contains(strtolower((string) $l->collateral_type), 'rem'));

            $cmCount = $cmLoans->count();
            $remCount = $remLoans->count();
            $loanAmount = (float) $bLoans->sum('requested_amount');

            // Encumbered: uses standard isEncumbered() method (including Notarized Certifications)
            $encCm = $cmLoans->filter(fn ($l) => $l->isEncumbered())->count();
            $encRem = $remLoans->filter(fn ($l) => $l->isEncumbered())->count();

            // Still On Process
            $opCm = $cmLoans->filter(fn ($l) => !$l->isEncumbered())->count();
            $opRem = $remLoans->filter(fn ($l) => !$l->isEncumbered())->count();

            // Deficiency
            $defCm = $cmLoans->filter(fn ($l) => in_array($l->sub_status, ['internal_deficiency', 'external_deficiency']))->count();
            $defRem = $remLoans->filter(fn ($l) => in_array($l->sub_status, ['internal_deficiency', 'external_deficiency']))->count();

            $totalLoansForBranch = $cmCount + $remCount;
            $totalEncForBranch = $encCm + $encRem;
            $ratePercent = $totalLoansForBranch > 0 ? round(($totalEncForBranch / $totalLoansForBranch) * 100, 1) : 0.0;

            // Grand total accumulations
            $totalCm += $cmCount;
            $totalRem += $remCount;
            $totalApprovedVolume += $loanAmount;
            $totalEncCm += $encCm;
            $totalEncRem += $encRem;
            $totalOpCm += $opCm;
            $totalOpRem += $opRem;
            $totalDefCm += $defCm;
            $totalDefRem += $defRem;

            $rows[] = [
                strtoupper($branch->name),
                $cmCount,
                $remCount,
                $loanAmount,
                $encCm,
                $encRem,
                $opCm,
                $opRem,
                $defCm,
                $defRem,
                $ratePercent . '%',
            ];
        }

        $grandTotalAccounts = $totalCm + $totalRem;
        $grandTotalEnc = $totalEncCm + $totalEncRem;
        $overallRate = $grandTotalAccounts > 0 ? round(($grandTotalEnc / $grandTotalAccounts) * 100, 1) : 0.0;

        $kpis = [
            ['label' => 'Total Branches Tracked', 'value' => number_format($branches->count()), 'icon' => 'heroicon-m-building-office-2'],
            ['label' => 'Total Approved Volume', 'value' => '₱' . number_format($totalApprovedVolume, 2), 'icon' => 'heroicon-m-banknotes'],
            ['label' => 'Fully Encumbered (CM/REM)', 'value' => "{$totalEncCm} CM • {$totalEncRem} REM", 'icon' => 'heroicon-m-shield-check'],
            ['label' => 'Still On-Process (CM/REM)', 'value' => "{$totalOpCm} CM • {$totalOpRem} REM", 'icon' => 'heroicon-m-arrow-path'],
            ['label' => 'Total Deficiencies Noted', 'value' => number_format($totalDefCm + $totalDefRem) . " ({$totalDefCm} CM / {$totalDefRem} REM)", 'icon' => 'heroicon-m-exclamation-triangle'],
            ['label' => 'Overall Encumbrance Rate', 'value' => $overallRate . '%', 'icon' => 'heroicon-m-chart-pie'],
        ];

        return [
            'type'        => 'branch_consolidated',
            'title'       => 'Consolidated Branch Report',
            'description' => 'Cross-branch executive comparison of loan volume, collateral encumbrance, on-process pipeline, and deficiency metrics',
            'kpis'        => $kpis,
            'headers'     => $headers,
            'rows'        => $rows,
            'metadata'    => $metadata,
            'total_volume'=> $totalApprovedVolume,
        ];
    }

    /**
     * 7. Official Registry of Deeds (ROD) EPEB Status & Pending Encumbrance Report
     */
    public static function getRodPendingStatusReport(array $filters, ?array $allowed, array $metadata): array
    {
        $rodBranchId = $filters['rod_branch_id'] ?? null;
        $branchOfficeId = $filters['branch_office_id'] ?? null;
        $collateralType = $filters['collateral_type'] ?? null;
        $year = $filters['year'] ?? null;
        $rodStatusId = $filters['rod_status_id'] ?? null;
        $statusFilter = $filters['status_filter'] ?? 'all';
        $search = $filters['search'] ?? null;

        $query = LoanApplication::query()
            ->with(['member', 'branchOffice', 'currentRodBranch', 'currentRodStatus', 'realEstateCollaterals.rodBranch'])
            ->whereIn('collateral_type', ['real_estate_mortgage', 'rem_amendment', 'rem_certification']);

        if (is_array($allowed)) {
            $query->whereIn('branch_office_id', $allowed);
        }

        if (filled($branchOfficeId)) {
            $query->where('branch_office_id', $branchOfficeId);
        }

        if (filled($rodBranchId)) {
            $query->where(function ($q) use ($rodBranchId) {
                $q->where('current_rod_branch_id', $rodBranchId)
                  ->orWhereHas('realEstateCollaterals', fn ($sq) => $sq->where('rod_branch_id', $rodBranchId));
            });
        }

        if (filled($collateralType)) {
            $query->where('collateral_type', $collateralType);
        }

        if (filled($year)) {
            $query->where(function ($q) use ($year) {
                $q->whereYear('rod_payment_date', $year)
                  ->orWhereYear('date_granted', $year)
                  ->orWhereYear('created_at', $year);
            });
        }

        if (filled($rodStatusId)) {
            $query->where('current_rod_status_id', $rodStatusId);
        }

        if ($statusFilter === 'pending') {
            $query->whereIn('status', ['ROD Paid', 'Rod Paid'])
                  ->whereNull('rod_release_date');
        } elseif ($statusFilter === 'released') {
            $query->where(function ($q) {
                $q->whereIn('status', ['ROD Released', 'Rod Released'])
                  ->orWhere(function ($sq) {
                      $sq->whereIn('status', ['ROD Paid', 'Rod Paid'])
                         ->whereNotNull('rod_release_date');
                  });
            });
        } else {
            // 'all': Only Real Estate Mortgage loans submitted to ROD (ROD Paid / ROD Released)
            $query->where(function ($q) {
                $q->whereIn('status', ['ROD Paid', 'Rod Paid', 'ROD Released', 'Rod Released'])
                  ->orWhere(function ($sq) {
                      $sq->whereNotNull('rod_payment_date')
                         ->whereNotIn('status', ['Initial Assessment', 'For Notary', 'Notarized', 'For BIR', 'BIR Paid', 'For ROD', 'Encumbered', 'Fully Paid', 'Deceased']);
                  });
            });
        }

        if (filled($search)) {
            $term = trim($search);
            $query->where(function ($q) use ($term) {
                $q->where('application_no', 'like', "%{$term}%")
                  ->orWhere('rod_entry_no', 'like', "%{$term}%")
                  ->orWhere('rod_release_entry_no', 'like', "%{$term}%")
                  ->orWhereHas('member', function ($mq) use ($term) {
                      $mq->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('member_no', 'like', "%{$term}%")
                        ->orWhere('cid', 'like', "%{$term}%");
                  })
                  ->orWhereHas('realEstateCollaterals', function ($rq) use ($term) {
                      $rq->where('title_number', 'like', "%{$term}%");
                  });
            });
        }

        $records = $query->orderByDesc('id')->get();

        $rows = [];
        $totalFee = 0.0;
        $pendingCount = 0;
        $releasedCount = 0;

        foreach ($records as $index => $record) {
            $rodOffice = $record->currentRodBranch?->name
                ?? $record->realEstateCollaterals->first()?->rodBranch?->name
                ?? '—';

            $titleNo = $record->realEstateCollaterals->pluck('title_number')->filter()->implode(', ') ?: '—';

            $isReleased = filled($record->rod_release_date) || in_array($record->status, ['ROD Released', 'Encumbered']);

            if ($isReleased) {
                $releasedCount++;
                $agingText = 'Released';
            } else {
                $pendingCount++;
                $baseDate = $record->rod_payment_date ?? ($record->status_changed_at ?? $record->created_at);
                $agingDays = abs((int) now()->diffInDays($baseDate));
                $agingText = $agingDays === 0 ? 'Today' : "{$agingDays} day(s)";
            }

            $rodStatusName = $record->currentRodStatus?->name ?? (
                (filled($record->rod_release_date) || in_array($record->status, ['ROD Released', 'Encumbered']))
                    ? 'Releasing'
                    : ((filled($record->rod_payment_date) || $record->status === 'ROD Paid')
                        ? (filled($record->rod_entry_no) ? 'Encoding' : 'Payment')
                        : 'Entry')
            );

            $amount = (float) ($record->rod_amount_paid ?? 0);
            $totalFee += $amount;

            $rows[] = [
                '#'                  => $index + 1,
                'Date Filed'         => $record->created_at ? $record->created_at->format('F d, Y') : '—',
                'Borrower Name'      => $record->member?->natural_full_name ?? $record->member?->full_name ?? '—',
                'OIC Branch'         => $record->branchOffice?->name ?? '—',
                'Loan Amount'        => (float) ($record->requested_amount ?? 0),
                'Type'               => match ($record->collateral_type) {
                    'real_estate_mortgage' => 'REM',
                    'rem_amendment'        => 'REM Amendment',
                    'rem_certification'    => 'REM Cert',
                    default                => ucwords(str_replace('_', ' ', (string) $record->collateral_type)),
                },
                'ROD Office'         => $rodOffice,
                'EPEB Entry #'       => filled($record->rod_entry_no) ? (string) $record->rod_entry_no : 'Pending Entry',
                'Title No.'          => $titleNo,
                'ROD Status'         => $rodStatusName,
                'Pending Days'       => $agingText,
            ];
        }

        $rodLabel = 'All ROD Branches';
        if (filled($rodBranchId)) {
            $rodLabel = \App\Models\RodBranch::find($rodBranchId)?->name ?? "ROD #{$rodBranchId}";
        }

        $metadata['date_label']       = filled($year) ? "Year {$year}" : ($metadata['date_label'] ?? 'All Records');
        $metadata['branch_label']     = filled($branchOfficeId) ? (\App\Models\BranchOffice::find($branchOfficeId)?->name ?? "Branch #{$branchOfficeId}") : ($metadata['branch_label'] ?? 'All Allowed Branches');
        $metadata['collateral_label'] = filled($collateralType) ? ucwords(str_replace('_', ' ', $collateralType)) : 'Real Estate Mortgage';
        $metadata['rod_label']        = $rodLabel;
        $metadata['year_label']       = filled($year) ? "Year {$year}" : 'All Years';
        $metadata['type_label']       = filled($collateralType) ? ucwords(str_replace('_', ' ', $collateralType)) : 'All Types';
        $metadata['rod_status_label'] = filled($rodStatusId) ? (\App\Models\RodStatus::find($rodStatusId)?->name ?? "Status #{$rodStatusId}") : 'All ROD Statuses';

        return [
            'type'        => 'rod_pending_status',
            'title'       => 'EPEB Status Report',
            'description' => 'Official Registry of Deeds (ROD) Electronic Primary Entry Book tracking & pending encumbrance monitoring',
            'metadata'    => $metadata,
            'headers'     => [
                '#', 'Date Filed', 'Borrower Name', 'OIC Branch', 'Loan Amount',
                'Type', 'ROD Office', 'EPEB Entry #', 'Title No.', 'ROD Status', 'Pending Days'
            ],
            'rows'        => $rows,
            'kpis'        => [
                ['label' => 'Total EPEB Records', 'value' => count($rows)],
                ['label' => 'Pending at ROD',     'value' => $pendingCount],
                ['label' => 'ROD Released',       'value' => $releasedCount],
                ['label' => 'Total ROD Fees Paid', 'value' => '₱' . number_format($totalFee, 2)],
            ],
        ];
    }

    /**
     * Generate and stream formatted Excel file using PhpSpreadsheet.
     */
    public static function exportToExcel(string $reportType, array $filters, $user = null): StreamedResponse
    {
        $data = self::getReportData($reportType, $filters, $user);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($data['title'], 0, 30));

        // Company Header (Rows 1-3)
        $sheet->setCellValue('A1', 'OIC PMIS v3 PORTAL — EXECUTIVE MANAGEMENT REPORTS');
        $sheet->setCellValue('A2', strtoupper($data['title']));
        $sheet->setCellValue('A3', "Coverage: {$data['metadata']['date_label']} | Branch: {$data['metadata']['branch_label']} | Collateral: {$data['metadata']['collateral_label']} | Run: {$data['metadata']['generated_at']}");

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('15803D');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('1E293B');
        $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(10)->getColor()->setRGB('64748B');

        // Table Headers (Row 5)
        $colCount = count($data['headers']);
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

        // Merge title rows across all columns so Column A (#) does not get stretched
        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->mergeCells("A2:{$lastColLetter}2");
        $sheet->mergeCells("A3:{$lastColLetter}3");

        $sheet->fromArray([$data['headers']], null, 'A5');

        // Style Table Header (Row 5)
        $sheet->getStyle("A5:{$lastColLetter}5")->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 10,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '15803D'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(26);

        // Data Rows starting at Row 6
        $currentRow = 6;
        foreach ($data['rows'] as $row) {
            $cleanedRow = array_map(function ($val) {
                if ($val === null || $val === '') {
                    return '—';
                }
                return $val;
            }, array_values($row));

            $sheet->fromArray([$cleanedRow], null, "A{$currentRow}", true);
            $sheet->getRowDimension($currentRow)->setRowHeight(20);
            $currentRow++;
        }

        $lastDataRow = $currentRow - 1;

        // Apply Borders to Data Range
        if ($lastDataRow >= 6) {
            $sheet->getStyle("A5:{$lastColLetter}{$lastDataRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => 'CBD5E1'],
                    ],
                ],
            ]);

            // Format numeric columns & add grand totals
            $sheet->setCellValue("A{$currentRow}", 'TOTAL:');
            $sheet->getRowDimension($currentRow)->setRowHeight(22);
            $sheet->getStyle("A{$currentRow}:{$lastColLetter}{$currentRow}")->applyFromArray([
                'font' => ['bold' => true],
                'borders' => [
                    'top'    => ['borderStyle' => Border::BORDER_THIN],
                    'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
                ],
            ]);

            foreach ($data['headers'] as $colIdx => $hText) {
                $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
                $isAmount = str_contains($hText, 'Amount') || str_contains($hText, '₱') || str_contains($hText, 'Volume');
                $isCount = in_array($hText, [
                    'Chattel Mortgage (CM)', 'Real Estate Mortgage (REM)',
                    'ENCUMBERED CM', 'ENCUMBERED REM',
                    'STILL ON PROCESS CM', 'STILL ON PROCESS REM',
                    'Deficiency CM', 'Deficiency REM'
                ]);

                if ($isAmount) {
                    $sheet->getStyle("{$cLetter}6:{$cLetter}{$lastDataRow}")
                        ->getNumberFormat()
                        ->setFormatCode('"₱"#,##0.00;("₱"#,##0.00);"₱"0.00');

                    $sheet->setCellValue("{$cLetter}{$currentRow}", "=SUM({$cLetter}6:{$cLetter}{$lastDataRow})");
                    $sheet->getStyle("{$cLetter}{$currentRow}")
                        ->getNumberFormat()
                        ->setFormatCode('"₱"#,##0.00;("₱"#,##0.00);"₱"0.00');
                    $sheet->getStyle("{$cLetter}6:{$cLetter}{$currentRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                } elseif ($isCount) {
                    $sheet->getStyle("{$cLetter}6:{$cLetter}{$lastDataRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');

                    $sheet->setCellValue("{$cLetter}{$currentRow}", "=SUM({$cLetter}6:{$cLetter}{$lastDataRow})");
                    $sheet->getStyle("{$cLetter}{$currentRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');
                    $sheet->getStyle("{$cLetter}6:{$cLetter}{$currentRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } elseif (str_contains($hText, 'Rate')) {
                    // Convert text percentage to real number so Excel renders cleanly without warnings
                    for ($r = 6; $r <= $lastDataRow; $r++) {
                        $val = $sheet->getCell("{$cLetter}{$r}")->getValue();
                        if (is_string($val) && str_contains($val, '%')) {
                            $numericRate = floatval(str_replace('%', '', $val)) / 100;
                            $sheet->setCellValue("{$cLetter}{$r}", $numericRate);
                        }
                    }

                    $totalEnc = 0;
                    $totalLoans = 0;
                    foreach ($data['rows'] as $r) {
                        $rVals = array_values($r);
                        $totalLoans += (float) ($rVals[1] ?? 0) + (float) ($rVals[2] ?? 0);
                        $totalEnc += (float) ($rVals[4] ?? 0) + (float) ($rVals[5] ?? 0);
                    }
                    $avgRate = $totalLoans > 0 ? round(($totalEnc / $totalLoans) * 100, 1) : 0.0;
                    $sheet->setCellValue("{$cLetter}{$currentRow}", $avgRate / 100);

                    $sheet->getStyle("{$cLetter}6:{$cLetter}{$currentRow}")
                        ->getNumberFormat()
                        ->setFormatCode('0%');
                    $sheet->getStyle("{$cLetter}6:{$cLetter}{$currentRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } elseif (str_contains($hText, 'Date') || str_contains($hText, 'Status') || str_contains($hText, 'Stage') || str_contains($hText, 'Account') || str_contains($hText, 'Collateral') || str_contains($hText, 'Classification') || str_contains($hText, 'Updated') || str_contains($hText, 'Plate')) {
                    $sheet->getStyle("{$cLetter}6:{$cLetter}{$currentRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            }
        }

        // Auto-fit column widths with intelligent constraints
        foreach (range(1, $colCount) as $col) {
            $cLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $headerName = $data['headers'][$col - 1] ?? '';

            if ($headerName === '#') {
                $sheet->getColumnDimension($cLetter)->setAutoSize(false)->setWidth(6);
                $sheet->getStyle("{$cLetter}5:{$cLetter}{$currentRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            } elseif ($headerName === 'EPEB Entry #' || $headerName === 'Loan Account') {
                $sheet->getColumnDimension($cLetter)->setAutoSize(false)->setWidth(18);
                $sheet->getStyle("{$cLetter}6:{$cLetter}{$lastDataRow}")
                    ->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
                $sheet->getStyle("{$cLetter}6:{$cLetter}{$lastDataRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            } elseif ($headerName === 'Title No.') {
                $sheet->getColumnDimension($cLetter)->setAutoSize(false)->setWidth(16);
                $sheet->getStyle("{$cLetter}6:{$cLetter}{$lastDataRow}")
                    ->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
            } elseif (str_contains($headerName, 'Remarks') || str_contains($headerName, 'Notes')) {
                $sheet->getColumnDimension($cLetter)->setAutoSize(false)->setWidth(36);
                $sheet->getStyle("{$cLetter}6:{$cLetter}{$lastDataRow}")
                    ->getAlignment()
                    ->setWrapText(true);
            } else {
                $sheet->getColumnDimension($cLetter)->setAutoSize(true);
            }
        }

        // Generate Filename
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $data['title']) . '_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Generate and stream formatted PDF file using DomPDF.
     */
    public static function exportToPdf(string $reportType, array $filters, $user = null): StreamedResponse
    {
        $data = self::getReportData($reportType, $filters, $user);

        $pdf = Pdf::loadView('filament.reports.pdf-report', ['data' => $data])
            ->setPaper('a4', 'landscape')
            ->setOption([
                'isRemoteEnabled'      => true,
                'defaultFont'          => 'sans-serif',
                'isHtml5ParserEnabled' => true,
            ]);

        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $data['title']) . '_' . now()->format('Ymd_His') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
