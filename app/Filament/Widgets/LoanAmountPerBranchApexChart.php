<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\InteractsWithDashboardFilters;
use App\Models\BranchOffice;
use App\Models\LoanApplication;
use Filament\Support\RawJs;
use Illuminate\Support\Facades\DB;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class LoanAmountPerBranchApexChart extends ApexChartWidget
{
    use InteractsWithDashboardFilters;

    protected static ?string $chartId = 'loanAmountPerBranchApexChart';

    protected static bool $isLazy = false;

    protected static ?string $heading = '🏦 Loan Amount per Branch';

    protected static ?int $sort = 4;

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

        $records = (clone $query)
            ->select([
                'branch_office_id',
                DB::raw('SUM(requested_amount) as total_amount'),
                DB::raw('COUNT(*) as total_count'),
            ])
            ->whereNotNull('branch_office_id')
            ->groupBy('branch_office_id')
            ->orderByDesc('total_amount')
            ->take(15)
            ->get();

        $branchIds = $records->pluck('branch_office_id')->filter()->toArray();
        $branchNames = BranchOffice::whereIn('id', $branchIds)->pluck('name', 'id')->toArray();

        $categories = [];
        $data = [];

        if ($records->isNotEmpty()) {
            foreach ($records as $record) {
                $name = $branchNames[$record->branch_office_id] ?? ('Branch #' . $record->branch_office_id);
                $categories[] = $name;
                $data[] = round((float) $record->total_amount, 2);
            }
        } else {
            // When no loans match the current date filter, display available branches with 0 so chart never looks empty
            $fallbackQuery = BranchOffice::query()->where('is_active', true)->orderBy('name');
            if (is_array($allowed)) {
                $fallbackQuery->whereIn('id', $allowed);
            }
            if ($selectedBranch) {
                $fallbackQuery->where('id', $selectedBranch);
            }
            $fallbackBranches = $fallbackQuery->take(10)->pluck('name')->toArray();
            if (empty($fallbackBranches)) {
                $categories = ['No Branches'];
                $data = [0];
            } else {
                foreach ($fallbackBranches as $bName) {
                    $categories[] = $bName;
                    $data[] = 0;
                }
            }
        }

        // Distinct color palette for distributed columns
        $colors = [
            '#6366f1', // Indigo
            '#3b82f6', // Blue
            '#0ea5e9', // Sky
            '#14b8a6', // Teal
            '#10b981', // Emerald
            '#f59e0b', // Amber
            '#f97316', // Orange
            '#ec4899', // Pink
            '#8b5cf6', // Violet
            '#06b6d4', // Cyan
            '#84cc16', // Lime
            '#e11d48', // Rose
            '#a855f7', // Purple
            '#d97706', // Yellow-Amber
            '#2563eb', // Royal Blue
        ];

        return [
            'chart' => [
                'type' => 'bar',
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
            'plotOptions' => [
                'bar' => [
                    'horizontal' => false,
                    'columnWidth' => count($categories) > 6 ? '55%' : '35%',
                    'borderRadius' => 6,
                    'borderRadiusApplication' => 'end',
                    'distributed' => true,
                    'dataLabels' => [
                        'position' => 'top', // show dataLabels on top of the bars
                    ],
                ],
            ],
            'series' => [
                [
                    'name' => 'Loan Amount (₱)',
                    'data' => $data,
                ],
            ],
            'xaxis' => [
                'categories' => $categories,
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                        'fontWeight' => 600,
                        'fontSize' => '12px',
                    ],
                    'rotate' => count($categories) > 8 ? -30 : 0,
                    'rotateAlways' => false,
                    'trim' => true,
                ],
                'axisBorder' => [
                    'show' => false,
                ],
            ],
            'yaxis' => [
                'title' => [
                    'text' => 'Total Amount (PHP)',
                    'style' => [
                        'fontFamily' => 'inherit',
                        'color' => '#6366f1',
                    ],
                ],
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                    ],
                ],
                'max' => (!empty($data) && max($data) > 0) ? (max($data) * 1.18) : null,
            ],
            'colors' => $colors,
            'legend' => [
                'show' => false,
            ],
            'dataLabels' => [
                'enabled' => true,
                'offsetY' => -20,
                'style' => [
                    'fontSize' => '11px',
                    'fontFamily' => 'inherit',
                    'fontWeight' => 700,
                    'colors' => ['#94a3b8'],
                ],
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
                dataLabels: {
                    formatter: function (val) {
                        if (!val || val === 0) return '';
                        if (val >= 1000000000) return '₱' + (val / 1000000000).toFixed(2) + 'B';
                        if (val >= 1000000) return '₱' + (val / 1000000).toFixed(2) + 'M';
                        if (val >= 1000) return '₱' + (val / 1000).toFixed(0) + 'K';
                        return '₱' + Number(val).toLocaleString();
                    }
                },
                yaxis: {
                    labels: {
                        formatter: function (val) {
                            if (val >= 1000000000) return '₱' + (val / 1000000000).toFixed(1) + 'B';
                            if (val >= 1000000) return '₱' + (val / 1000000).toFixed(1) + 'M';
                            if (val >= 1000) return '₱' + (val / 1000).toFixed(0) + 'K';
                            return '₱' + Number(val).toLocaleString();
                        }
                    }
                },
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
