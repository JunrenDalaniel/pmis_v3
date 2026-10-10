<?php

namespace App\Filament\Widgets;

use App\Models\LoanApplication;
use Filament\Widgets\ChartWidget;

class DocumentPipelineChartWidget extends ChartWidget
{
    public static function canView(): bool
    {
        return false;
    }

    protected static bool $isLazy = false;

    protected ?string $heading = 'Document Tracking Pipeline Distribution';

    protected static ?int $sort = 4;

    protected ?string $maxHeight = '280px';

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'md' => 1,
        'xl' => 2,
    ];

    protected function getData(): array
    {
        $user = auth()->user();
        $allowed = $user?->getAllowedBranchIds();

        $query = LoanApplication::where('approval_status', 'Approved');
        if (is_array($allowed)) {
            $query->whereIn('branch_office_id', $allowed);
        }

        $stages = [
            'For Notary'   => ['For Notary'],
            'Notarized'    => ['Notarized'],
            'For BIR'      => ['For BIR', 'For Bir'],
            'BIR Paid'     => ['BIR Paid', 'Bir Paid'],
            'For ROD'      => ['For ROD', 'For Rod'],
            'ROD Paid'     => ['ROD Paid', 'Rod Paid'],
            'For LTO'      => ['For LTO', 'For Lto'],
            'Encumbered'   => ['Encumbered'],
        ];

        $labels = [];
        $data = [];

        foreach ($stages as $label => $values) {
            $labels[] = $label;
            $data[] = (clone $query)->whereIn('status', $values)->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Active Loans in Stage',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(245, 158, 11, 0.75)',  // For Notary - Amber
                        'rgba(59, 130, 246, 0.75)',   // Notarized - Blue
                        'rgba(234, 88, 12, 0.75)',    // For BIR - Orange
                        'rgba(16, 185, 129, 0.75)',  // BIR Paid - Emerald
                        'rgba(168, 85, 247, 0.75)',  // For ROD - Purple
                        'rgba(6, 182, 212, 0.75)',   // ROD Paid - Cyan
                        'rgba(249, 115, 22, 0.75)',  // For LTO - Amber-orange
                        'rgba(34, 197, 94, 0.85)',   // Encumbered - Green
                    ],
                    'borderColor' => [
                        '#f59e0b',
                        '#3b82f6',
                        '#ea580c',
                        '#10b981',
                        '#a855f7',
                        '#06b6d4',
                        '#f97316',
                        '#22c55e',
                    ],
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
