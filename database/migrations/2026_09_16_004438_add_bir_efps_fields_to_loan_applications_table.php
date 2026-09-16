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
            $table->string('bir_reference_no')->nullable()->after('bir_payment_date');
            $table->string('bir_bank_confirmation_no')->nullable()->after('bir_reference_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn([
                'bir_reference_no',
                'bir_bank_confirmation_no',
            ]);
        });
    }
};
