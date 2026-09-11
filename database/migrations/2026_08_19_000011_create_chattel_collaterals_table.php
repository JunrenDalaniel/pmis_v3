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
        Schema::create('chattel_collaterals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained('loan_applications')->cascadeOnDelete();
            $table->string('owner_name')->nullable();
            $table->string('mv_file_no')->nullable();
            $table->string('plate_no')->nullable();
            $table->string('engine_no')->nullable();
            $table->string('chassis_no')->nullable();
            $table->string('year_model')->nullable();
            $table->text('vehicle_description')->nullable();
            $table->string('encumbered_to')->nullable()->default('N/A');
            $table->date('registration_renewal_date')->nullable();
            $table->date('expire_date')->nullable();
            $table->string('cm_status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chattel_collaterals');
    }
};
