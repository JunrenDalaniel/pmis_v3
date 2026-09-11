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
        Schema::table('branch_offices', function (Blueprint $table) {
            if (!Schema::hasColumn('branch_offices', 'manager_name')) {
                $table->string('manager_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('branch_offices', 'manager_user_id')) {
                $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete()->after('manager_name');
            }
            if (!Schema::hasColumn('branch_offices', 'manager_email')) {
                $table->string('manager_email')->nullable()->after('manager_user_id');
            }
            if (!Schema::hasColumn('branch_offices', 'manager_phone_no')) {
                $table->string('manager_phone_no')->nullable()->after('manager_email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branch_offices', function (Blueprint $table) {
            if (Schema::hasColumn('branch_offices', 'manager_user_id')) {
                $table->dropForeign(['manager_user_id']);
                $table->dropColumn('manager_user_id');
            }
            if (Schema::hasColumn('branch_offices', 'manager_name')) {
                $table->dropColumn('manager_name');
            }
            if (Schema::hasColumn('branch_offices', 'manager_email')) {
                $table->dropColumn('manager_email');
            }
            if (Schema::hasColumn('branch_offices', 'manager_phone_no')) {
                $table->dropColumn('manager_phone_no');
            }
        });
    }
};
