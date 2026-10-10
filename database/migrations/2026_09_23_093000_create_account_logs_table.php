<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->foreignId('branch_office_id')->nullable()->constrained('branch_offices')->nullOnDelete();
            $table->string('branch_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('host')->nullable();
            $table->string('device')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable()->index();
            $table->timestamp('login_at')->nullable()->index();
            $table->timestamp('logout_at')->nullable();
            $table->string('status', 30)->default('active')->index(); // 'active', 'logged_out', 'failed'
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['branch_office_id', 'login_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_logs');
    }
};
