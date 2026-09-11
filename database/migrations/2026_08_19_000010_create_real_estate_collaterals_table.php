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
        Schema::create('real_estate_collaterals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained('loan_applications')->cascadeOnDelete();
            $table->string('title_details')->nullable();
            $table->string('title_number')->nullable();
            $table->string('title_owner_name')->nullable();
            $table->string('tax_dec_land')->nullable();
            $table->string('land_owner_name')->nullable();
            $table->string('pin_land')->nullable();
            $table->json('building_tax_declarations')->nullable();
            $table->string('tax_dec_bldg')->nullable();
            $table->string('bldg_owner_name')->nullable();
            $table->string('pin_bldg')->nullable();
            $table->string('rem_status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('real_estate_collaterals');
    }
};
