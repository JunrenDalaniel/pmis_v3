<?php

namespace App\Filament\Widgets;

use App\Models\LoanApplication;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = auth()->user();
        $allowed = $user?->getAllowedBranchIds();

        // Base scoped query for current user's branch visibility
        $baseQuery = LoanApplication::query();
        if (is_array($allowed)) {
            $baseQuery->whereIn('branch_office_id', $allowed);
        }

        // Helper formatter for compact peso amounts in card descriptions
        $formatShort = function ($amount): string {
            $amt = (float) $amount;
            if ($amt >= 1_000_000) {
                return '₱' . number_format($amt / 1_000_000, 2) . 'M';
            } elseif ($amt >= 1_000) {
                return '₱' . number_format($amt / 1_000, 1) . 'K';
            }
            return '₱' . number_format($amt, 0);
        };

        // Reusable filters for Collateral Types (CM vs REM)
        $filterCm = fn ($q) => $q->where(function ($sub) {
            $sub->where('collateral_type', 'like', '%chattel%')
                ->orWhere('collateral_type', 'like', '%cm%');
        });

        $filterRem = fn ($q) => $q->where(function ($sub) {
            $sub->where('collateral_type', 'like', '%real%')
                ->orWhere('collateral_type', 'like', '%rem%');
        });

        // 1. Approved Loans (Amount & Count with CM & REM breakdown)
        $approvedQuery = (clone $baseQuery)->where('approval_status', 'Approved');
        $approvedCount = (clone $approvedQuery)->count();
        $approvedSum   = (clone $approvedQuery)->sum('requested_amount');
        $appCmCount    = $filterCm(clone $approvedQuery)->count();
        $appCmSum      = $filterCm(clone $approvedQuery)->sum('requested_amount');
        $appRemCount   = $filterRem(clone $approvedQuery)->count();
        $appRemSum     = $filterRem(clone $approvedQuery)->sum('requested_amount');

        // 2. Disapproved Loans (Amount & Count with CM & REM breakdown)
        $disapprovedQuery = (clone $baseQuery)->where('approval_status', 'Disapproved');
        $disapprovedCount = (clone $disapprovedQuery)->count();
        $disapprovedSum   = (clone $disapprovedQuery)->sum('requested_amount');
        $disCmCount       = $filterCm(clone $disapprovedQuery)->count();
        $disCmSum         = $filterCm(clone $disapprovedQuery)->sum('requested_amount');
        $disRemCount      = $filterRem(clone $disapprovedQuery)->count();
        $disRemSum        = $filterRem(clone $disapprovedQuery)->sum('requested_amount');

        // 3. Pending Approvals (Count & Amount with CM & REM breakdown)
        $pendingQuery = (clone $baseQuery)->where('approval_status', 'Pending Approval');
        $pendingCount = (clone $pendingQuery)->count();
        $pendingSum   = (clone $pendingQuery)->sum('requested_amount');
        $penCmCount   = $filterCm(clone $pendingQuery)->count();
        $penCmSum     = $filterCm(clone $pendingQuery)->sum('requested_amount');
        $penRemCount  = $filterRem(clone $pendingQuery)->count();
        $penRemSum    = $filterRem(clone $pendingQuery)->sum('requested_amount');

        // 4. On-Process Records (Active in Document Pipeline with CM & REM breakdown)
        $onProcessStages = [
            'For Notary', 'Notarized', 'For BIR', 'For Bir',
            'BIR Paid', 'Bir Paid', 'For ROD', 'For Rod',
            'ROD Paid', 'Rod Paid', 'ROD Released', 'For LTO', 'For Lto',
            "For Assessor's",
        ];
        $onProcessQuery = (clone $baseQuery)
            ->where('approval_status', 'Approved')
            ->whereIn('status', $onProcessStages);
        $onProcessCount = (clone $onProcessQuery)->count();
        $onProcessSum   = (clone $onProcessQuery)->sum('requested_amount');
        $opCmCount      = $filterCm(clone $onProcessQuery)->count();
        $opCmSum        = $filterCm(clone $onProcessQuery)->sum('requested_amount');
        $opRemCount     = $filterRem(clone $onProcessQuery)->count();
        $opRemSum       = $filterRem(clone $onProcessQuery)->sum('requested_amount');

        // 5. Encumbered Records (Completed & Secured with CM & REM breakdown)
        $encumberedQuery = (clone $baseQuery)->where('status', 'Encumbered');
        $encumberedCount = (clone $encumberedQuery)->count();
        $encumberedSum   = (clone $encumberedQuery)->sum('requested_amount');
        $encCmCount      = $filterCm(clone $encumberedQuery)->count();
        $encCmSum        = $filterCm(clone $encumberedQuery)->sum('requested_amount');
        $encRemCount     = $filterRem(clone $encumberedQuery)->count();
        $encRemSum       = $filterRem(clone $encumberedQuery)->sum('requested_amount');

        // 6. With Deficiency Records (Internal & External with CM & REM breakdown)
        $deficiencyQuery = (clone $baseQuery)
            ->whereIn('sub_status', ['internal_deficiency', 'external_deficiency']);
        $deficiencyCount = (clone $deficiencyQuery)->count();
        $deficiencySum   = (clone $deficiencyQuery)->sum('requested_amount');
        $defCmCount      = $filterCm(clone $deficiencyQuery)->count();
        $defCmSum        = $filterCm(clone $deficiencyQuery)->sum('requested_amount');
        $defRemCount     = $filterRem(clone $deficiencyQuery)->count();
        $defRemSum       = $filterRem(clone $deficiencyQuery)->sum('requested_amount');

        $recordLabel = fn (int $c) => number_format($c) . ' ' . ($c === 1 ? 'Record' : 'Records');

        return [
            Stat::make('Approved Loans', '₱' . number_format($approvedSum, 2))
                ->description("{$approvedCount} " . ($approvedCount === 1 ? 'Loan' : 'Loans') . " • CM: {$appCmCount} (" . $formatShort($appCmSum) . ") | REM: {$appRemCount} (" . $formatShort($appRemSum) . ")")
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Disapproved Loans', '₱' . number_format($disapprovedSum, 2))
                ->description("{$disapprovedCount} " . ($disapprovedCount === 1 ? 'Loan' : 'Loans') . " • CM: {$disCmCount} (" . $formatShort($disCmSum) . ") | REM: {$disRemCount} (" . $formatShort($disRemSum) . ")")
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make('Pending Approval', $recordLabel($pendingCount))
                ->description("CM: {$penCmCount} (" . $formatShort($penCmSum) . ") | REM: {$penRemCount} (" . $formatShort($penRemSum) . ")")
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingCount > 0 ? 'warning' : 'gray'),

            Stat::make('On Process Records', $recordLabel($onProcessCount))
                ->description("CM: {$opCmCount} (" . $formatShort($opCmSum) . ") | REM: {$opRemCount} (" . $formatShort($opRemSum) . ")")
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info'),

            Stat::make('Encumbered Records', $recordLabel($encumberedCount))
                ->description("CM: {$encCmCount} (" . $formatShort($encCmSum) . ") | REM: {$encRemCount} (" . $formatShort($encRemSum) . ")")
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('success'),

            Stat::make('With Deficiency', $recordLabel($deficiencyCount))
                ->description("CM: {$defCmCount} (" . $formatShort($defCmSum) . ") | REM: {$defRemCount} (" . $formatShort($defRemSum) . ")")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($deficiencyCount > 0 ? 'danger' : 'success'),
        ];
    }
}
