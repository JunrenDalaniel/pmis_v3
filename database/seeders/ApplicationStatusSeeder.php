<?php

namespace Database\Seeders;

use App\Models\ApplicationStatus;
use Illuminate\Database\Seeder;

class ApplicationStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Truncate or reset table to seed clean sequential workflow stages
        ApplicationStatus::query()->delete();

        $statuses = [
            ['name' => 'Initial Assessment', 'code' => 'initial_assessment', 'color' => 'gray', 'sort_order' => 1],
            ['name' => 'For Notary', 'code' => 'for_notary', 'color' => 'warning', 'sort_order' => 2],
            ['name' => 'Notarized', 'code' => 'notarized', 'color' => 'warning', 'sort_order' => 3],
            ['name' => 'For BIR', 'code' => 'for_bir', 'color' => 'info', 'sort_order' => 4],
            ['name' => 'BIR Paid', 'code' => 'bir_paid', 'color' => 'info', 'sort_order' => 5],
            ['name' => 'For ROD', 'code' => 'for_rod', 'color' => 'primary', 'sort_order' => 6],
            ['name' => 'ROD Paid', 'code' => 'rod_paid', 'color' => 'success', 'sort_order' => 7],
            ['name' => 'ROD Released', 'code' => 'rod_released', 'color' => 'success', 'sort_order' => 8],
            ['name' => 'For LTO', 'code' => 'for_lto', 'color' => 'info', 'sort_order' => 9],
            ['name' => "For Assessor's", 'code' => 'for_assessors', 'color' => 'warning', 'sort_order' => 10],
            ['name' => 'Encumbered', 'code' => 'encumbered', 'color' => 'success', 'sort_order' => 11],
            ['name' => 'Fully Paid', 'code' => 'fully_paid', 'color' => 'success', 'sort_order' => 12],
            ['name' => 'Deceased', 'code' => 'deceased', 'color' => 'danger', 'sort_order' => 13],
        ];

        foreach ($statuses as $status) {
            ApplicationStatus::updateOrCreate(
                ['code' => $status['code']],
                $status
            );
        }
    }
}
