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
            if (!Schema::hasColumn('loan_applications', 'loan_account_no')) {
                $table->string('loan_account_no')->nullable()->after('application_no');
            }
            if (!Schema::hasColumn('loan_applications', 'date_granted')) {
                $table->date('date_granted')->nullable()->after('requested_amount');
            }
            if (!Schema::hasColumn('loan_applications', 'maturity_date')) {
                $table->date('maturity_date')->nullable()->after('date_granted');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            if (Schema::hasColumn('loan_applications', 'maturity_date')) {
                $table->dropColumn('maturity_date');
            }
            if (Schema::hasColumn('loan_applications', 'date_granted')) {
                $table->dropColumn('date_granted');
            }
            if (Schema::hasColumn('loan_applications', 'loan_account_no')) {
                $table->dropColumn('loan_account_no');
            }
        });
    }
};
