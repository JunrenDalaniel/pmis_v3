<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_logs', function (Blueprint $table) {
            $table->string('role_name')->nullable()->after('user_email')->index();
        });
    }

    public function down(): void
    {
        Schema::table('account_logs', function (Blueprint $table) {
            $table->dropColumn('role_name');
        });
    }
};
