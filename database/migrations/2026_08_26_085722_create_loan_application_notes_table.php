<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loan_application_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained('loan_applications')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage')->nullable();
            $table->text('note');
            $table->timestamps();
        });

        // Backfill existing loan_applications.notes
        $existing = DB::table('loan_applications')->whereNotNull('notes')->where('notes', '!=', '')->get();
        foreach ($existing as $app) {
            DB::table('loan_application_notes')->insert([
                'loan_application_id' => $app->id,
                'user_id'             => $app->created_by_user_id,
                'stage'               => $app->status ?? 'Initial Assessment',
                'note'                => $app->notes,
                'created_at'          => $app->created_at ?? now(),
                'updated_at'          => $app->updated_at ?? now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_application_notes');
    }
};
