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
        Schema::table('real_estate_collaterals', function (Blueprint $table) {
            if (!Schema::hasColumn('real_estate_collaterals', 'location')) {
                $table->text('location')->nullable()->after('title_owner_name');
            }
            if (!Schema::hasColumn('real_estate_collaterals', 'rod_branch_id')) {
                $table->foreignId('rod_branch_id')->nullable()->constrained('rod_branches')->nullOnDelete()->after('location');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('real_estate_collaterals', function (Blueprint $table) {
            if (Schema::hasColumn('real_estate_collaterals', 'rod_branch_id')) {
                $table->dropForeign(['rod_branch_id']);
                $table->dropColumn('rod_branch_id');
            }
            if (Schema::hasColumn('real_estate_collaterals', 'location')) {
                $table->dropColumn('location');
            }
        });
    }
};
