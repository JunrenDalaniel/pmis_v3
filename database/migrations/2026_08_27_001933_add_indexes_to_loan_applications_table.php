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
            $table->index('approval_status');
            $table->index('status');
            $table->index('sub_status');
            $table->index(['approval_status', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropIndex(['approval_status']);
            $table->dropIndex(['status']);
            $table->dropIndex(['sub_status']);
            $table->dropIndex(['approval_status', 'status']);
        });
    }
};
