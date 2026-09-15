<?php

namespace App\Filament\Widgets;

use App\Models\LoanApplication;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CmStatsOverviewWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected ?string $heading = '🚗 Chattel Mortgage (CM) Status';

    protected ?string $description = 'Tracking of On-Process, Encumbered, and Deficiency records for vehicle & chattel collaterals';

    protected function getStats(): array
    {
        $user = auth()->user();
        $allowed = $user?->getAllowedBranchIds();

        // Base scoped query for Chattel Mortgage (CM) - Approved / Released loans only
        $baseQuery = LoanApplication::query()
            ->where('approval_status', 'Approved')
            ->where(function ($sub) {
                $sub->where('collateral_type', 'like', '%chattel%')
                    ->orWhere('collateral_type', 'like', '%cm%');
            });

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

        $recordLabel = fn (int $c) => number_format($c) . ' ' . ($c === 1 ? 'Record' : 'Records');

        // 1. CM On-Process Records
        $onProcessStages = [
            'For Notary', 'Notarized', 'For BIR', 'For Bir',
            'BIR Paid', 'Bir Paid', 'For ROD', 'For Rod',
            'ROD Paid', 'Rod Paid', 'ROD Released', 'For LTO', 'For Lto',
            "For Assessor's",
        ];
        $onProcessQuery = (clone $baseQuery)
            ->where('approval_status', 'Approved')
            ->whereIn('status', $onProcessStages);
        $opCount = (clone $onProcessQuery)->count();
        $opSum   = (clone $onProcessQuery)->sum('requested_amount');

        // 2. CM Encumbered Records
        $encQuery = (clone $baseQuery)->where('status', 'Encumbered');
        $encCount = (clone $encQuery)->count();
        $encSum   = (clone $encQuery)->sum('requested_amount');

        // 3. CM With Deficiency Records
        $defQuery = (clone $baseQuery)->whereIn('sub_status', ['internal_deficiency', 'external_deficiency']);
        $defCount = (clone $defQuery)->count();
        $defSum   = (clone $defQuery)->sum('requested_amount');

        return [
            Stat::make('CM On-Process', $recordLabel($opCount))
                ->description($formatShort($opSum) . ' Active in Document Pipeline')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info'),

            Stat::make('CM Encumbered', $recordLabel($encCount))
                ->description($formatShort($encSum) . ' Completed & Secured')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('success'),

            Stat::make('CM With Deficiency', $recordLabel($defCount))
                ->description($defCount > 0 ? $formatShort($defSum) . ' Action Required' : 'No Deficiencies')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($defCount > 0 ? 'danger' : 'success'),
        ];
    }
}
