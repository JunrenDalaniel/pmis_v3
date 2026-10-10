<?php

namespace App\Filament\Resources\ActivityLogResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Activitylog\Models\Activity;

class AuditLogStatsWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected function getBaseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = Activity::query();
        $user = auth()->user();

        if ($user) {
            $allowedBranches = $user->getAllowedBranchIds();
            if ($allowedBranches !== null) {
                $branchNames = \App\Models\BranchOffice::whereIn('id', $allowedBranches)->pluck('name')->toArray();

                $query->where(function ($q) use ($allowedBranches, $branchNames, $user) {
                    $q->whereIn('properties->branch_office_id', $allowedBranches)
                      ->orWhereIn('properties->branch', $branchNames)
                      ->orWhereHasMorph('causer', [\App\Models\User::class], function ($userQuery) use ($allowedBranches, $user) {
                          $userQuery->whereIn('branch_office_id', $allowedBranches)
                                    ->orWhere('id', $user->id);
                      })
                      ->orWhereHasMorph('subject', [\App\Models\LoanApplication::class], function ($loanQuery) use ($allowedBranches) {
                          $loanQuery->whereIn('branch_office_id', $allowedBranches);
                      })
                      ->orWhereHasMorph('subject', [\App\Models\Member::class], function ($memberQuery) use ($allowedBranches) {
                          $memberQuery->whereIn('branch_office_id', $allowedBranches);
                      });
                });
            }
        }

        return $query;
    }

    protected function getStats(): array
    {
        $today = today();
        $base = $this->getBaseQuery();

        $changesToday = (clone $base)->whereDate('created_at', $today)->count();
        $totalChanges = (clone $base)->count();

        $approvedToday = (clone $base)->whereDate('created_at', $today)
            ->where(function ($q) {
                $q->where('description', 'like', '%APPROVED%')
                  ->orWhere('properties->attributes->approval_status', 'Approved');
            })
            ->count();
        $totalApproved = (clone $base)->where(function ($q) {
                $q->where('description', 'like', '%APPROVED%')
                  ->orWhere('properties->attributes->approval_status', 'Approved');
            })
            ->count();

        $disapprovedToday = (clone $base)->whereDate('created_at', $today)
            ->where(function ($q) {
                $q->where('description', 'like', '%DISAPPROVED%')
                  ->orWhere('properties->attributes->approval_status', 'Disapproved');
            })
            ->count();
        $totalDisapproved = (clone $base)->where(function ($q) {
                $q->where('description', 'like', '%DISAPPROVED%')
                  ->orWhere('properties->attributes->approval_status', 'Disapproved');
            })
            ->count();

        $activeStaffToday = (int) ((clone $base)->whereDate('created_at', $today)
            ->whereNotNull('causer_id')
            ->selectRaw('COUNT(DISTINCT causer_id) as aggregate')
            ->value('aggregate') ?? 0);
        $totalStaff = (int) ((clone $base)->whereNotNull('causer_id')
            ->selectRaw('COUNT(DISTINCT causer_id) as aggregate')
            ->value('aggregate') ?? 0);

        return [
            Stat::make('Total Activity Records', (string) $totalChanges)
                ->description($changesToday > 0 ? "{$changesToday} changes logged today" : 'No new changes today')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('info'),

            Stat::make('Loans Approved', (string) $totalApproved)
                ->description($approvedToday > 0 ? "{$approvedToday} approved today" : 'No approvals logged today')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($totalApproved > 0 ? 'success' : 'gray'),

            Stat::make('Loans Disapproved', (string) $totalDisapproved)
                ->description($disapprovedToday > 0 ? "{$disapprovedToday} with deficiencies today" : 'No deficiencies logged today')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($totalDisapproved > 0 ? 'danger' : 'gray'),

            Stat::make('Active Staff in Logs', (string) ($activeStaffToday > 0 ? $activeStaffToday : $totalStaff))
                ->description($activeStaffToday > 0 ? "{$activeStaffToday} staff active today" : "{$totalStaff} total staff on record")
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),
        ];
    }
}
