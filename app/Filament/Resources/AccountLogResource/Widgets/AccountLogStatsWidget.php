<?php

namespace App\Filament\Resources\AccountLogResource\Widgets;

use App\Models\AccountLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountLogStatsWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected function getBaseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = AccountLog::query();
        $user = auth()->user();

        if ($user) {
            $allowedBranches = $user->getAllowedBranchIds();
            if ($allowedBranches !== null) {
                $query->where(function ($q) use ($allowedBranches, $user) {
                    $q->whereIn('branch_office_id', $allowedBranches)
                      ->orWhere('user_id', $user->id);
                });
            }
        }

        return $query;
    }

    protected function getStats(): array
    {
        $base = $this->getBaseQuery();
        $activeUsers = (int) ((clone $base)->active()->whereNotNull('user_id')->selectRaw('COUNT(DISTINCT user_id) as aggregate')->value('aggregate') ?? 0);
        $loginsToday = (clone $base)->today()->where('status', '!=', 'failed')->count();
        $failedToday = (clone $base)->today()->where('status', 'failed')->count();

        $sessionDesc = match (true) {
            $activeUsers === 0 => 'No staff currently online',
            $activeUsers === 1 => '1 staff member currently online',
            default => "{$activeUsers} staff members currently online",
        };

        return [
            Stat::make('Active Users Now', (string) $activeUsers)
                ->description($sessionDesc)
                ->descriptionIcon('heroicon-m-signal')
                ->color($activeUsers > 0 ? 'success' : 'gray'),

            Stat::make('Total Logins Today', (string) $loginsToday)
                ->description('Successful sessions initiated today')
                ->descriptionIcon('heroicon-m-arrow-right-end-on-rectangle')
                ->color('info'),

            Stat::make('Failed Attempts Today', (string) $failedToday)
                ->description($failedToday > 0 ? 'Suspicious or incorrect credentials' : 'No failed logins')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($failedToday > 0 ? 'danger' : 'success'),
        ];
    }
}
