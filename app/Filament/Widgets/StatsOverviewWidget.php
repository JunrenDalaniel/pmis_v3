<?php

namespace App\Filament\Widgets;

use App\Models\BranchOffice;
use App\Models\Member;
use App\Models\RodBranch;
use App\Models\RodStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $memberCount       = Cache::remember('stats_total_members', 120, fn () => Member::count());
        $branchCount       = Cache::remember('stats_total_branches', 300, fn () => BranchOffice::count());
        $rodBranchCount    = Cache::remember('stats_total_rod_branches', 300, fn () => RodBranch::count());
        $rodStatusCount    = Cache::remember('stats_total_rod_statuses', 300, fn () => RodStatus::count());

        return [
            Stat::make('Total Members', $memberCount)
                ->description('Registered System Members')
                ->descriptionIcon('heroicon-m-identification')
                ->color('success'),
            Stat::make('OIC Branch Offices', $branchCount)
                ->description('Active Branch Locations')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('info'),
            Stat::make('ROD Offices', $rodBranchCount)
                ->description('Registry of Deeds Branches')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning'),
            Stat::make('Document Process Steps', $rodStatusCount)
                ->description('Workflow Process Statuses')
                ->descriptionIcon('heroicon-m-list-bullet')
                ->color('primary'),
        ];
    }
}
