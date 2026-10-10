<?php

namespace App\Filament\Concerns;

use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

trait InteractsWithDashboardFilters
{
    use InteractsWithPageFilters;

    /**
     * Resolve start and end date from page filters based on date_preset and custom dates.
     *
     * @return array{0: ?string, 1: ?string} [startDate, endDate]
     */
    protected function getFilterDateRange(): array
    {
        $preset = $this->pageFilters['date_preset'] ?? 'this_month';
        $startDate = $this->pageFilters['startDate'] ?? null;
        $endDate = $this->pageFilters['endDate'] ?? null;

        return match ($preset) {
            'this_month' => [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ],
            'last_month' => [
                now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'midyear' => [
                // Midyear is H1: January 1 to June 30 of the current year
                now()->startOfYear()->toDateString(),
                now()->startOfYear()->addMonths(5)->endOfMonth()->toDateString(),
            ],
            'this_year' => [
                now()->startOfYear()->toDateString(),
                now()->endOfYear()->toDateString(),
            ],
            'last_year' => [
                now()->subYear()->startOfYear()->toDateString(),
                now()->subYear()->endOfYear()->toDateString(),
            ],
            'all' => [null, null],
            'custom' => [$startDate, $endDate],
            default => [$startDate, $endDate],
        };
    }

    /**
     * Get the active date preset label for widget descriptions.
     */
    protected function getFilterPresetLabel(): string
    {
        $preset = $this->pageFilters['date_preset'] ?? 'this_month';

        return match ($preset) {
            'this_month'   => 'This Month (' . now()->format('F Y') . ')',
            'last_month'   => 'Last Month (' . now()->subMonthNoOverflow()->format('F Y') . ')',
            'midyear'      => 'Midyear (' . now()->startOfYear()->format('M d') . ' - ' . now()->startOfYear()->addMonths(5)->endOfMonth()->format('M d, Y') . ')',
            'this_year'    => 'This Year (' . now()->year . ')',
            'last_year'    => 'Last Year (' . (now()->year - 1) . ')',
            'custom'       => 'Custom Range',
            'all'          => 'All Record',
            default        => 'All Record',
        };
    }

    protected function getFilterBranchId(): ?string
    {
        return $this->pageFilters['branch_office_id'] ?? null;
    }

    protected function getFilterCollateralType(): ?string
    {
        return $this->pageFilters['collateral_type'] ?? null;
    }
}
