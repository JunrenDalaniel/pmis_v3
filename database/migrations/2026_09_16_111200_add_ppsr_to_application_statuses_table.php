<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ApplicationStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $statuses = [
            ['name' => 'Initial Assessment', 'code' => 'initial_assessment', 'color' => 'gray', 'sort_order' => 1],
            ['name' => 'For Notary', 'code' => 'for_notary', 'color' => 'warning', 'sort_order' => 2],
            ['name' => 'Notarized', 'code' => 'notarized', 'color' => 'warning', 'sort_order' => 3],
            ['name' => 'For BIR', 'code' => 'for_bir', 'color' => 'info', 'sort_order' => 4],
            ['name' => 'BIR Paid', 'code' => 'bir_paid', 'color' => 'info', 'sort_order' => 5],
            ['name' => 'For ROD', 'code' => 'for_rod', 'color' => 'primary', 'sort_order' => 6],
            ['name' => 'ROD Paid', 'code' => 'rod_paid', 'color' => 'success', 'sort_order' => 7],
            ['name' => 'ROD Released', 'code' => 'rod_released', 'color' => 'success', 'sort_order' => 8],
            ['name' => 'For PPSR', 'code' => 'for_ppsr', 'color' => 'warning', 'sort_order' => 9],
            ['name' => 'PPSR Paid', 'code' => 'ppsr_paid', 'color' => 'info', 'sort_order' => 10],
            ['name' => 'For LTO', 'code' => 'for_lto', 'color' => 'info', 'sort_order' => 11],
            ['name' => "For Assessor's", 'code' => 'for_assessors', 'color' => 'warning', 'sort_order' => 12],
            ['name' => 'Encumbered', 'code' => 'encumbered', 'color' => 'success', 'sort_order' => 13],
            ['name' => 'Fully Paid', 'code' => 'fully_paid', 'color' => 'success', 'sort_order' => 14],
            ['name' => 'Deceased', 'code' => 'deceased', 'color' => 'danger', 'sort_order' => 15],
        ];

        foreach ($statuses as $st) {
            ApplicationStatus::updateOrCreate(
                ['code' => $st['code']],
                [
                    'name' => $st['name'],
                    'color' => $st['color'],
                    'sort_order' => $st['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        ApplicationStatus::whereIn('code', ['for_ppsr', 'ppsr_paid'])->delete();
    }
};
