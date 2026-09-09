<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices, their lines, and the payments against them.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * FOUR RULES, AND THE SCHEMA IS SHAPED BY ALL FOUR
 *
 * 1. AMOUNTS ARE INTEGERS OF MINOR UNITS. Paise for INR, cents for USD. No
 *    column here is a float or a decimal — see App\Support\Money.
 *
 * 2. THERE IS NO `total` COLUMN, AND NO `amount_paid` COLUMN. A total is the
 *    sum of the lines and the paid figure is the sum of the payments. Stored,
 *    each is a number that can disagree with what produced it, and the first
 *    time it does the argument is with a client about money.
 *
 * 3. THERE IS NO `status` COLUMN EITHER. Draft, sent, partial, paid, overdue
 *    and cancelled are derived from the dates, the lines and the payments —
 *    nobody sets an invoice to "paid", they record the payment that makes it
 *    paid. The two things that ARE stored are the two that cannot be derived:
 *    when it was sent, and whether it was cancelled.
 *
 * 4. NUMBERS ARE GAPLESS AND NOTHING IS EVER DELETED. A cancelled invoice keeps
 *    its number. A missing number in a sequence is the first thing an auditor
 *    asks about and "we deleted it" is the wrong answer in every jurisdiction —
 *    which is why there is no delete route anywhere above this file, and why
 *    the number is issued inside the transaction that writes the row.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // INV-2026-014. Unique, sequential, and never reissued.
            $table->string('number', 32)->unique();

            // Restricted: an invoice whose client had been removed is a record
            // nobody can explain. Clients are never deleted anyway.
            $table->foreignId('client_id')->constrained()->restrictOnDelete();

            // Nullable: not every invoice is against a project.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            /*
             * Per invoice, not per company. A client billed in USD gets an
             * invoice in USD, and a set of invoices in two currencies has no
             * single total — see App\Support\MoneyBag, which is why the KPI
             * tiles carry bags rather than numbers.
             */
            $table->string('currency', 3)->default('INR');

            $table->date('invoice_date');
            $table->date('due_date');

            /*
             * The two stored facts that cannot be derived.
             *
             * `sent_at` is what separates a draft from a sent invoice, and it
             * is a timestamp rather than a boolean because "when did we send
             * it" is the question a chase email needs answered.
             *
             * `cancelled_at` keeps the number in the sequence while taking the
             * invoice out of what is owed. Withdrawal is a cancellation, never
             * a delete.
             */
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('client_id');
            $table->index('due_date');
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();

            // Cascading: a line has no meaning without its invoice, and nothing
            // points at a line.
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->string('description', 300);

            /*
             * Quantity is an integer. Fractional quantities — 2.5 hours — would
             * need a rate and a rounding rule, and rounding a rate against a
             * quantity is where an invoice stops agreeing with itself by a
             * paisa. Hours are billed as a line saying so.
             */
            $table->unsignedInteger('quantity')->default(1);

            // Minor units. The line's amount is quantity × this, computed.
            $table->unsignedBigInteger('unit_price_minor');

            // The order somebody typed them in, which is the order they read.
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->index(['invoice_id', 'position']);
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('amount_minor');

            $table->date('received_on');
            $table->string('method', 40);

            /*
             * The bank's reference, and the reason this table exists rather
             * than an `amount_paid` column: a payment is a real event with a
             * date and a trace, and reconciling one against a bank statement
             * needs all three.
             */
            $table->string('reference', 120)->nullable();

            // Who recorded it. Nullable and nullOnDelete: the payment happened
            // whether or not the person who typed it in is still here.
            $table->foreignId('recorded_by')->nullable()
                ->constrained('employees')->nullOnDelete();

            $table->timestamps();

            $table->index(['invoice_id', 'received_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
