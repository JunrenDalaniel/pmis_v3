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
            $table->string('cancellation_mode')->nullable()->after('mortgage_release_status'); // 'member' or 'coop'
            $table->string('clearance_no')->nullable()->after('cancellation_mode');
            $table->string('cancellation_or_no')->nullable()->after('cancellation_entry_no');
            $table->decimal('cancellation_fee', 12, 2)->nullable()->after('cancellation_or_no');
            $table->string('claimant_type')->nullable()->after('title_released_to'); // 'borrower' or 'authorized_rep'
            $table->string('rep_id_details')->nullable()->after('claimant_type');
            $table->text('rep_spa_details')->nullable()->after('rep_id_details');
            $table->boolean('is_title_returned')->default(false)->after('rep_spa_details');
            $table->boolean('is_cancellation_deed_returned')->default(false)->after('is_title_returned');
            $table->boolean('is_sec_cert_returned')->default(false)->after('is_cancellation_deed_returned');
            $table->boolean('is_promissory_note_returned')->default(false)->after('is_sec_cert_returned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn([
                'cancellation_mode',
                'clearance_no',
                'cancellation_or_no',
                'cancellation_fee',
                'claimant_type',
                'rep_id_details',
                'rep_spa_details',
                'is_title_returned',
                'is_cancellation_deed_returned',
                'is_sec_cert_returned',
                'is_promissory_note_returned',
            ]);
        });
    }
};
