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
            $table->string('ppsr_notice_no')->nullable()->after('rod_release_date');
            $table->string('ppsr_receipt_no')->nullable()->after('ppsr_notice_no');
            $table->date('ppsr_payment_date')->nullable()->after('ppsr_receipt_no');
            $table->decimal('ppsr_amount_paid', 12, 2)->nullable()->after('ppsr_payment_date');
            $table->string('ppsr_transaction_no')->nullable()->after('ppsr_amount_paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn([
                'ppsr_notice_no',
                'ppsr_receipt_no',
                'ppsr_payment_date',
                'ppsr_amount_paid',
                'ppsr_transaction_no',
            ]);
        });
    }
};
