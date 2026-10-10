<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\InteractsWithDashboardFilters;
use App\Models\LoanApplication;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class CollateralBreakdownApexChart extends ApexChartWidget
{
    use InteractsWithDashboardFilters;

    protected static ?string $chartId = 'collateralBreakdownApexChart';

    protected static bool $isLazy = false;

    protected static ?string $heading = '🥧 Collateral Portfolio Share';

    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    protected function getOptions(): array
    {
        $user = auth()->user();
        $allowed = $user?->getAllowedBranchIds();

        $query = LoanApplication::query();

        if (is_array($allowed)) {
            $query->whereIn('branch_office_id', $allowed);
        }

        [$startDate, $endDate] = $this->getFilterDateRange();
        $selectedBranch = $this->getFilterBranchId();
        $selectedCollateral = $this->getFilterCollateralType();

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        if ($selectedBranch) {
            $query->where('branch_office_id', $selectedBranch);
        }
        if ($selectedCollateral) {
            if ($selectedCollateral === 'real_estate_mortgage') {
                $query->where(fn ($q) => $q->where('collateral_type', 'like', '%real%')->orWhere('collateral_type', 'like', '%rem%'));
            } elseif ($selectedCollateral === 'chattel_mortgage') {
                $query->where(fn ($q) => $q->where('collateral_type', 'like', '%chattel%')->orWhere('collateral_type', 'like', '%cm%'));
            }
        }

        $records = $query->get(['collateral_type']);

        $cmCount = 0;
        $remCount = 0;
        $otherCount = 0;

        foreach ($records as $record) {
            $type = strtolower((string) $record->collateral_type);
            if (str_contains($type, 'chattel') || str_contains($type, 'cm')) {
                $cmCount++;
            } elseif (str_contains($type, 'real') || str_contains($type, 'rem')) {
                $remCount++;
            } else {
                $otherCount++;
            }
        }

        // Avoid empty series crash in ApexCharts
        $series = [$cmCount, $remCount, $otherCount];
        $labels = ['Chattel Mortgage (CM)', 'Real Estate (REM)', 'Other / Unsecured'];

        return [
            'chart' => [
                'type' => 'donut',
                'height' => 320,
                'animations' => [
                    'enabled' => true,
                    'easing' => 'easeinout',
                    'speed' => 700,
                ],
            ],
            'series' => $series,
            'labels' => $labels,
            'colors' => ['#3b82f6', '#f59e0b', '#10b981'],
            'plotOptions' => [
                'pie' => [
                    'donut' => [
                        'size' => '65%',
                        'labels' => [
                            'show' => true,
                            'total' => [
                                'show' => true,
                                'label' => 'Total Collaterals',
                                'fontFamily' => 'inherit',
                                'fontWeight' => 600,
                            ],
                        ],
                    ],
                ],
            ],
            'legend' => [
                'position' => 'bottom',
                'labels' => [
                    'fontFamily' => 'inherit',
                ],
            ],
            'dataLabels' => [
                'enabled' => true,
            ],
            'stroke' => [
                'width' => 2,
                'colors' => ['#fff'],
            ],
        ];
    }
}
