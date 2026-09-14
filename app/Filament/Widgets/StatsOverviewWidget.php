<?php

namespace App\Filament\Widgets;

use App\Models\LoanApplication;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
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

        // 1. Total Released Loans (Amount & Count)
        $releasedQuery = (clone $baseQuery)->where('approval_status', 'Approved');
        $releasedCount  = (clone $releasedQuery)->count();
        $releasedAmount = (clone $releasedQuery)->sum('requested_amount');
        $formattedReleased = '₱' . number_format($releasedAmount, 2);

        // 2. In-Pipeline Documents (Pending completion)
        $pipelineStages = [
            'For Notary', 'Notarized', 'For BIR', 'For Bir',
            'BIR Paid', 'Bir Paid', 'For ROD', 'For Rod',
        ];
        $pipelineQuery = (clone $baseQuery)
            ->where('approval_status', 'Approved')
            ->whereIn('status', $pipelineStages);
        $pipelineCount  = (clone $pipelineQuery)->count();
        $pipelineAmount = (clone $pipelineQuery)->sum('requested_amount');
        $formattedPipeline = '₱' . number_format($pipelineAmount, 2);

        // 3. Pending Approval Queue
        $pendingCount = (clone $baseQuery)->where('approval_status', 'Pending Approval')->count();

        // 4. Overdue / Deficiency Alerts (>10 days in stage or internal/external deficiency)
        $alertCount = (clone $baseQuery)
            ->where('approval_status', 'Approved')
            ->where(function ($q) {
                $q->whereIn('sub_status', ['internal_deficiency', 'external_deficiency'])
                  ->orWhere(function ($sub) {
                      $sub->whereNotNull('status_changed_at')
                          ->where('status_changed_at', '<=', now()->subDays(10))
                          ->where('status', '!=', 'Encumbered');
                  });
            })
            ->count();

        return [
            Stat::make('Total Released Loans', $formattedReleased)
                ->description("{$releasedCount} Approved & Released Records")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('In-Pipeline Documents', (string) $pipelineCount)
                ->description("{$formattedPipeline} In Active Processing")
                ->descriptionIcon('heroicon-m-document-duplicate')
                ->color('info'),

            Stat::make('Pending Approval', (string) $pendingCount)
                ->description($pendingCount > 0 ? 'Requires Head Office Decision' : 'Queue is Clear')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingCount > 0 ? 'warning' : 'gray'),

            Stat::make('Deficiencies / Overdue', (string) $alertCount)
                ->description($alertCount > 0 ? 'Needs Urgent Attention' : 'All Workflows on Track')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($alertCount > 0 ? 'danger' : 'success'),
        ];
    }
}
