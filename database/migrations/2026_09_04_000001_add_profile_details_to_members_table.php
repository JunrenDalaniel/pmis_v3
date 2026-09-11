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
        Schema::table('members', function (Blueprint $table) {
            if (!Schema::hasColumn('members', 'place_of_birth')) {
                $table->string('place_of_birth')->nullable()->after('birth_date');
            }
            if (!Schema::hasColumn('members', 'tin')) {
                $table->string('tin')->nullable()->after('civil_status');
            }
            if (!Schema::hasColumn('members', 'sss_gsis')) {
                $table->string('sss_gsis')->nullable()->after('tin');
            }
            if (!Schema::hasColumn('members', 'occupation')) {
                $table->string('occupation')->nullable()->after('address');
            }
            if (!Schema::hasColumn('members', 'office_number')) {
                $table->string('office_number')->nullable()->after('occupation');
            }
            if (!Schema::hasColumn('members', 'employer_name')) {
                $table->string('employer_name')->nullable()->after('office_number');
            }
            if (!Schema::hasColumn('members', 'employment_status')) {
                $table->string('employment_status')->nullable()->after('employer_name');
            }
            if (!Schema::hasColumn('members', 'office_address')) {
                $table->text('office_address')->nullable()->after('employment_status');
            }
            if (!Schema::hasColumn('members', 'remarks')) {
                $table->text('remarks')->nullable()->after('office_address');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $columns = [
                'place_of_birth',
                'tin',
                'sss_gsis',
                'occupation',
                'office_number',
                'employer_name',
                'employment_status',
                'office_address',
                'remarks',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('members', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
