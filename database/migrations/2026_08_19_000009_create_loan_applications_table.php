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
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_no')->unique();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('branch_office_id')->nullable()->constrained('branch_offices')->nullOnDelete();
            $table->decimal('requested_amount', 15, 2);
            $table->enum('collateral_type', ['real_estate_mortgage', 'chattel_mortgage'])->default('real_estate_mortgage');
            $table->string('status')->default('Pending Assessment');
            $table->foreignId('current_rod_status_id')->nullable()->constrained('rod_statuses')->nullOnDelete();
            $table->foreignId('current_rod_branch_id')->nullable()->constrained('rod_branches')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
