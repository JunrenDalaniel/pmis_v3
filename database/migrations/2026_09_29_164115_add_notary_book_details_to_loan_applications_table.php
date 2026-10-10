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
            $table->string('notary_doc_no')->nullable()->after('notarized_date');
            $table->string('notary_page_no')->nullable()->after('notary_doc_no');
            $table->string('notary_book_no')->nullable()->after('notary_page_no');
            $table->string('notary_series_no')->nullable()->after('notary_book_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn([
                'notary_doc_no',
                'notary_page_no',
                'notary_book_no',
                'notary_series_no',
            ]);
        });
    }
};
