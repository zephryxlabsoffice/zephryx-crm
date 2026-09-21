<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices become uploaded documents, like payslips (decided 2026-09-11,
 * built 2026-09-21).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THIS REVERSES THE LINE-ITEM BUILDER, AND SAYS SO ON PURPOSE
 *
 * "No GST billing fields. Invoices are uploaded like payslips, not generated.
 * Amount, due date and bank payments are still recorded by hand, so overdue
 * and balance keep working... This reverses the built line-item invoice
 * builder." (plan doc, Clients section, 2026-09-11.)
 *
 * `invoice_lines` drops outright rather than being kept unused. Nothing in
 * production has shipped against it — migrations here have never been run
 * against a real database (see the plan doc's "Environment note") — so there
 * is no data to preserve and no reason to carry a dead table forward.
 *
 * `invoice_payments` is UNTOUCHED. "Bank payments are still recorded by
 * hand" is the one piece of the old shape the decision keeps, and the
 * ledger it already was — a row per payment, never an `amount_paid` column —
 * is exactly what "so overdue and balance keep working" describes.
 *
 * `amount_minor` REPLACES THE SUM OF THE LINES, not `total()` itself.
 * `Invoice::total()` stays a method, not a column, for the same reason it
 * always was one: SalaryRecord already proved the shape — `net_minor` is
 * typed once, by hand, and nothing above it treats that as less trustworthy
 * than a computed sum. An invoice's total was never "derived" in a sense
 * that mattered for correctness; it was arithmetic over numbers a person
 * typed into three fields instead of one. One field is the same fact.
 *
 * THE FOUR `document_*` COLUMNS mirror `salary_records`' `payslip_*`
 * columns exactly, because the two are the same kind of write: a document
 * this application did not produce, stored via App\Support\Documents\
 * DocumentStore, addressed by a path that may be a local disk key or (going
 * forward) a `drive:` file id. `document_added_by` is nullable and
 * nullOnDelete for the reason `payslip_added_by` is: the upload happened
 * whether or not the person who did it still has an account.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('invoice_lines');

        Schema::table('invoices', function (Blueprint $table) {
            // Typed by hand, like a payslip's net figure — not summed from
            // rows that no longer exist.
            $table->unsignedBigInteger('amount_minor')->after('currency')->default(0);

            $table->string('document_path')->nullable()->after('notes');
            $table->string('document_name')->nullable()->after('document_path');
            $table->unsignedBigInteger('document_bytes')->nullable()->after('document_name');
            $table->timestamp('document_added_at')->nullable()->after('document_bytes');
            $table->foreignId('document_added_by')->nullable()
                ->after('document_added_at')
                ->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_added_by');
            $table->dropColumn(['amount_minor', 'document_path', 'document_name', 'document_bytes', 'document_added_at']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description', 300);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->index(['invoice_id', 'position']);
        });
    }
};
