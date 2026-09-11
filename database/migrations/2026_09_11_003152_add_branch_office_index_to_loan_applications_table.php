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
            // Composite index for branch-scoped list queries (most common filter combo)
            $table->index(['approval_status', 'branch_office_id'], 'loan_apps_approval_branch_idx');
            // Standalone branch index for branch-only filter queries
            $table->index('branch_office_id', 'loan_apps_branch_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropIndex('loan_apps_approval_branch_idx');
            $table->dropIndex('loan_apps_branch_idx');
        });
    }
};
