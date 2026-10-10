<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\InteractsWithDashboardFilters;
use App\Models\LoanApplication;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Illuminate\Support\Facades\DB;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class LoanVolumeApexChart extends ApexChartWidget
{
    use InteractsWithDashboardFilters;

    protected static ?string $chartId = 'loanVolumeApexChart';

    protected static bool $isLazy = false;

    protected static ?string $heading = '📈 Loan Volume & Portfolio Trend';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected function getOptions(): array
    {
        $user = auth()->user();
        $allowed = $user?->getAllowedBranchIds();

        $query = LoanApplication::query()
            ->where('approval_status', 'Approved');

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

        // Determine grouping: by month (default) or by day if narrow date range
        $start = $startDate ? Carbon::parse($startDate) : now()->subMonths(5)->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate) : now()->endOfMonth();

        $diffDays = $start->diffInDays($end);

        $categories = [];
        $volumeData = [];

        if ($diffDays <= 45) {
            // Group by Day
            $current = $start->copy();
            $records = (clone $query)
                ->select([
                    DB::raw('DATE(created_at) as period'),
                    DB::raw('SUM(requested_amount) as total_volume'),
                ])
                ->groupBy('period')
                ->get()
                ->keyBy('period');

            while ($current->lte($end)) {
                $key = $current->format('Y-m-d');
                $label = $current->format('M d');
                $categories[] = $label;
                $row = $records->get($key);
                $volumeData[] = $row ? round((float) $row->total_volume, 2) : 0;
                $current->addDay();
            }
        } else {
            // Group by Month
            $current = $start->copy()->startOfMonth();
            $records = (clone $query)
                ->select([
                    DB::raw("DATE_FORMAT(created_at, '%Y-%m') as period"),
                    DB::raw('SUM(requested_amount) as total_volume'),
                ])
                ->groupBy('period')
                ->get()
                ->keyBy('period');

            while ($current->lte($end)) {
                $key = $current->format('Y-m');
                $label = $current->format('M Y');
                $categories[] = $label;
                $row = $records->get($key);
                $volumeData[] = $row ? round((float) $row->total_volume, 2) : 0;
                $current->addMonth();
            }
        }

        return [
            'chart' => [
                'type' => 'area',
                'height' => 340,
                'toolbar' => [
                    'show' => false,
                ],
                'animations' => [
                    'enabled' => true,
                    'easing' => 'easeinout',
                    'speed' => 700,
                ],
            ],
            'series' => [
                [
                    'name' => 'Loan Volume (₱)',
                    'data' => $volumeData,
                ],
            ],
            'xaxis' => [
                'categories' => $categories,
                'tickAmount' => count($categories) > 15 ? 12 : count($categories),
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                        'fontWeight' => 500,
                    ],
                    'rotate' => count($categories) > 15 ? -25 : 0,
                    'rotateAlways' => false,
                    'hideOverlappingLabels' => true,
                ],
                'axisBorder' => [
                    'show' => false,
                ],
            ],
            'yaxis' => [
                [
                    'title' => [
                        'text' => 'Approved Volume (PHP)',
                        'style' => [
                            'fontFamily' => 'inherit',
                            'color' => '#10b981',
                        ],
                    ],
                    'labels' => [
                        'style' => [
                            'fontFamily' => 'inherit',
                        ],
                    ],
                ],
            ],
            'colors' => ['#10b981'],
            'stroke' => [
                'curve' => 'smooth',
                'width' => 3,
            ],
            'fill' => [
                'type' => 'gradient',
                'gradient' => [
                    'shadeIntensity' => 1,
                    'opacityFrom' => 0.45,
                    'opacityTo' => 0.05,
                    'stops' => [0, 90, 100],
                ],
            ],
            'dataLabels' => [
                'enabled' => false,
            ],
            'grid' => [
                'borderColor' => '#e2e8f0',
                'strokeDashArray' => 4,
            ],
        ];
    }

    protected function extraJsOptions(): ?RawJs
    {
        return RawJs::make(<<<'JS'
            {
                yaxis: [
                    {
                        labels: {
                            formatter: function (val) {
                                if (val >= 1000000) return '₱' + (val / 1000000).toFixed(1) + 'M';
                                if (val >= 1000) return '₱' + (val / 1000).toFixed(0) + 'K';
                                return '₱' + Number(val).toLocaleString();
                            }
                        }
                    }
                ],
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return '₱' + Number(val).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    }
                }
            }
        JS);
    }
}
