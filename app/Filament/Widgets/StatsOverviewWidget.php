<?php

namespace App\Filament\Widgets;

use App\Models\LoanApplication;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected ?string $heading = '📊 Executive Loan Overview';

    protected ?string $description = 'Summary of approved, disapproved, and pending loan applications';

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
        ];
    }
}
