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

        // 1. Approved Loans (Amount & Count with REM & CM breakdown)
        $approvedQuery = (clone $baseQuery)->where('approval_status', 'Approved');
        $approvedCount = (clone $approvedQuery)->count();
        $approvedSum   = (clone $approvedQuery)->sum('requested_amount');
        $appRemCount   = (clone $approvedQuery)->where('collateral_type', 'like', '%real%')->count();
        $appRemSum     = (clone $approvedQuery)->where('collateral_type', 'like', '%real%')->sum('requested_amount');
        $appCmCount    = (clone $approvedQuery)->where('collateral_type', 'like', '%chattel%')->count();
        $appCmSum      = (clone $approvedQuery)->where('collateral_type', 'like', '%chattel%')->sum('requested_amount');

        // 2. Disapproved Loans (Amount & Count with REM & CM breakdown)
        $disapprovedQuery = (clone $baseQuery)->where('approval_status', 'Disapproved');
        $disapprovedCount = (clone $disapprovedQuery)->count();
        $disapprovedSum   = (clone $disapprovedQuery)->sum('requested_amount');
        $disRemCount      = (clone $disapprovedQuery)->where('collateral_type', 'like', '%real%')->count();
        $disRemSum        = (clone $disapprovedQuery)->where('collateral_type', 'like', '%real%')->sum('requested_amount');
        $disCmCount       = (clone $disapprovedQuery)->where('collateral_type', 'like', '%chattel%')->count();
        $disCmSum         = (clone $disapprovedQuery)->where('collateral_type', 'like', '%chattel%')->sum('requested_amount');

        // 3. Pending Approvals
        $pendingQuery = (clone $baseQuery)->where('approval_status', 'Pending Approval');
        $pendingCount = (clone $pendingQuery)->count();
        $pendingSum   = (clone $pendingQuery)->sum('requested_amount');
        $penRemCount  = (clone $pendingQuery)->where('collateral_type', 'like', '%real%')->count();
        $penCmCount   = (clone $pendingQuery)->where('collateral_type', 'like', '%chattel%')->count();

        // 4. On-Process Records (Active in Document Pipeline)
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

        // 5. Encumbered Records
        $encumberedQuery = (clone $baseQuery)->where('status', 'Encumbered');
        $encumberedCount = (clone $encumberedQuery)->count();
        $encumberedSum   = (clone $encumberedQuery)->sum('requested_amount');

        // 6. With Deficiency Records
        $deficiencyQuery = (clone $baseQuery)
            ->whereIn('sub_status', ['internal_deficiency', 'external_deficiency']);
        $deficiencyCount = (clone $deficiencyQuery)->count();
        $internalCount   = (clone $deficiencyQuery)->where('sub_status', 'internal_deficiency')->count();
        $externalCount   = (clone $deficiencyQuery)->where('sub_status', 'external_deficiency')->count();

        return [
            Stat::make('Approved Loans', '₱' . number_format($approvedSum, 2))
                ->description("{$approvedCount} Loans • REM: {$appRemCount} (" . $formatShort($appRemSum) . ") | CM: {$appCmCount} (" . $formatShort($appCmSum) . ")")
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Disapproved Loans', '₱' . number_format($disapprovedSum, 2))
                ->description("{$disapprovedCount} Loans • REM: {$disRemCount} (" . $formatShort($disRemSum) . ") | CM: {$disCmCount} (" . $formatShort($disCmSum) . ")")
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make('Pending Approval', (string) $pendingCount . ' Records')
                ->description($formatShort($pendingSum) . " Queue • REM: {$penRemCount} | CM: {$penCmCount}")
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingCount > 0 ? 'warning' : 'gray'),

            Stat::make('On Process Records', (string) $onProcessCount . ' Records')
                ->description($formatShort($onProcessSum) . ' In Document Pipeline')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info'),

            Stat::make('Encumbered Records', (string) $encumberedCount . ' Records')
                ->description($formatShort($encumberedSum) . ' 100% Completed & Secured')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('success'),

            Stat::make('With Deficiency', (string) $deficiencyCount . ' Records')
                ->description($deficiencyCount > 0 ? "{$internalCount} Internal • {$externalCount} External Flag" : 'No Deficiencies')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($deficiencyCount > 0 ? 'danger' : 'success'),
        ];
    }
}
