<?php

namespace App\Filament\Widgets;

use App\Models\LoanApplication;
use Filament\Widgets\ChartWidget;

class CollateralDistributionChartWidget extends ChartWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'Collateral Distribution';

    protected static ?int $sort = 5;

    protected ?string $maxHeight = '280px';

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'md' => 1,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $user = auth()->user();
        $allowed = $user?->getAllowedBranchIds();

        $query = LoanApplication::query();
        if (is_array($allowed)) {
            $query->whereIn('branch_office_id', $allowed);
        }

        $allLoans = $query->get(['collateral_type']);

        $cmCount = 0;
        $remCount = 0;
        $otherCount = 0;

        foreach ($allLoans as $loan) {
            $type = strtolower((string) $loan->collateral_type);
            if (str_contains($type, 'chattel') || str_contains($type, 'cm')) {
                $cmCount++;
            } elseif (str_contains($type, 'real estate') || str_contains($type, 'rem')) {
                $remCount++;
            } else {
                $otherCount++;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Collateral Share',
                    'data' => [$cmCount, $remCount, $otherCount],
                    'backgroundColor' => [
                        '#3b82f6', // Blue - CM
                        '#f59e0b', // Amber - REM
                        '#10b981', // Green - Other
                    ],
                    'hoverOffset' => 6,
                ],
            ],
            'labels' => ['Chattel Mortgage (CM)', 'Real Estate (REM)', 'Other / Unsecured'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}