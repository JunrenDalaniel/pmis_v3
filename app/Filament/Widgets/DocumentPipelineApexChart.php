<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\InteractsWithDashboardFilters;
use App\Models\LoanApplication;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class DocumentPipelineApexChart extends ApexChartWidget
{
    use InteractsWithDashboardFilters;

    protected static ?string $chartId = 'documentPipelineApexChart';

    protected static bool $isLazy = false;

    protected static ?string $heading = '📑 Document Processing Pipeline';

    protected static ?int $sort = 7;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

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

        $stages = [
            'For Notary' => ['For Notary'],
            'Notarized'  => ['Notarized'],
            'For BIR'    => ['For BIR', 'For Bir'],
            'BIR Paid'   => ['BIR Paid', 'Bir Paid'],
            'For ROD'    => ['For ROD', 'For Rod'],
            'ROD Paid'   => ['ROD Paid', 'Rod Paid'],
            'For LTO'    => ['For LTO', 'For Lto'],
            'Encumbered' => ['Encumbered'],
        ];

        $categories = [];
        $data = [];

        foreach ($stages as $label => $values) {
            $categories[] = $label;
            $data[] = (clone $query)->whereIn('status', $values)->count();
        }

        $colors = [
            '#f59e0b', // For Notary - Amber
            '#3b82f6', // Notarized - Blue
            '#ea580c', // For BIR - Orange
            '#10b981', // BIR Paid - Emerald
            '#a855f7', // For ROD - Purple
            '#06b6d4', // ROD Paid - Cyan
            '#f43f5e', // For LTO - Rose
            '#22c55e', // Encumbered - Green
        ];

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 320,
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
                    'horizontal' => true,
                    'borderRadius' => 6,
                    'borderRadiusApplication' => 'end',
                    'distributed' => true,
                    'dataLabels' => [
                        'position' => 'top',
                    ],
                ],
            ],
            'series' => [
                [
                    'name' => 'Active Loans in Stage',
                    'data' => $data,
                ],
            ],
            'xaxis' => [
                'categories' => $categories,
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                    ],
                ],
            ],
            'yaxis' => [
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                        'fontWeight' => 500,
                    ],
                ],
            ],
            'colors' => $colors,
            'legend' => [
                'show' => false,
            ],
            'dataLabels' => [
                'enabled' => true,
                'offsetX' => 20,
                'style' => [
                    'fontSize' => '12px',
                    'fontWeight' => 'bold',
                    'colors' => ['#475569'],
                ],
            ],
            'grid' => [
                'borderColor' => '#e2e8f0',
                'strokeDashArray' => 4,
            ],
        ];
    }
}
