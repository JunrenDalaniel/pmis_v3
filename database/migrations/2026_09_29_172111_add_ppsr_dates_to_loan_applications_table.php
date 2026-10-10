<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->date('ppsr_initial_notice_date')->nullable()->after('ppsr_notice_no');
            $table->date('ppsr_ctc_notice_date')->nullable()->after('ppsr_initial_notice_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn([
                'ppsr_initial_notice_date',
                'ppsr_ctc_notice_date',
            ]);
        });
    }
};
