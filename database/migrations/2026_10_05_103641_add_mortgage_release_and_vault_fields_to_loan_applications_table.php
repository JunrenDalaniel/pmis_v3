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
            $table->string('vault_location')->nullable()->after('annotation_no');
            $table->string('mortgage_release_status')->default('active')->nullable()->after('vault_location');
            $table->date('cancellation_date')->nullable()->after('mortgage_release_status');
            $table->string('cancellation_entry_no')->nullable()->after('cancellation_date');
            $table->date('title_released_date')->nullable()->after('cancellation_entry_no');
            $table->string('title_released_to')->nullable()->after('title_released_date');
            $table->string('title_released_by')->nullable()->after('title_released_to');
            $table->text('title_release_remarks')->nullable()->after('title_released_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn([
                'vault_location',
                'mortgage_release_status',
                'cancellation_date',
                'cancellation_entry_no',
                'title_released_date',
                'title_released_to',
                'title_released_by',
                'title_release_remarks',
            ]);
        });
    }
};
