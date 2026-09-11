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
            if (!Schema::hasColumn('loan_applications', 'sub_status')) {
                $table->string('sub_status')->default('none')->after('status');
            }
            if (!Schema::hasColumn('loan_applications', 'deficiency_notes')) {
                $table->text('deficiency_notes')->nullable()->after('sub_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            if (Schema::hasColumn('loan_applications', 'deficiency_notes')) {
                $table->dropColumn('deficiency_notes');
            }
            if (Schema::hasColumn('loan_applications', 'sub_status')) {
                $table->dropColumn('sub_status');
            }
        });
    }
};
