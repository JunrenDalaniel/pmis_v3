<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            // Stage tracking — when the current status started
            $table->timestamp('status_changed_at')->nullable()->after('status');

            // Notary Stage
            $table->string('notary_name')->nullable()->after('status_changed_at');
            $table->date('notarized_date')->nullable()->after('notary_name');

            // BIR Stage
            $table->string('bir_office')->nullable()->after('notarized_date');
            $table->date('bir_payment_date')->nullable()->after('bir_office');
            $table->string('bir_or_no')->nullable()->after('bir_payment_date');
            $table->decimal('bir_amount_paid', 12, 2)->nullable()->after('bir_or_no');

            // ROD Stage
            $table->string('rod_entry_no')->nullable()->after('bir_amount_paid');
            $table->date('rod_payment_date')->nullable()->after('rod_entry_no');
            $table->string('rod_or_no')->nullable()->after('rod_payment_date');
            $table->decimal('rod_amount_paid', 12, 2)->nullable()->after('rod_or_no');
            $table->date('rod_release_date')->nullable()->after('rod_amount_paid');

            // LTO Stage (Chattel Mortgage only)
            $table->string('lto_office')->nullable()->after('rod_release_date');
            $table->date('lto_payment_date')->nullable()->after('lto_office');
            $table->string('lto_or_no')->nullable()->after('lto_payment_date');
            $table->decimal('lto_amount_paid', 12, 2)->nullable()->after('lto_or_no');
            $table->date('lto_release_date')->nullable()->after('lto_amount_paid');

            // Assessor's Office Stage
            $table->string('assessors_office')->nullable()->after('lto_release_date');
            $table->date('assessors_received_date')->nullable()->after('assessors_office');
            $table->date('assessors_release_date')->nullable()->after('assessors_received_date');

            // Encumbered Stage
            $table->date('encumbered_date')->nullable()->after('assessors_release_date');
            $table->string('annotation_no')->nullable()->after('encumbered_date');
        });
    }

    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn([
                'status_changed_at',
                'notary_name', 'notarized_date',
                'bir_office', 'bir_payment_date', 'bir_or_no', 'bir_amount_paid',
                'rod_entry_no', 'rod_payment_date', 'rod_or_no', 'rod_amount_paid', 'rod_release_date',
                'lto_office', 'lto_payment_date', 'lto_or_no', 'lto_amount_paid', 'lto_release_date',
                'assessors_office', 'assessors_received_date', 'assessors_release_date',
                'encumbered_date', 'annotation_no',
            ]);
        });
    }
};
