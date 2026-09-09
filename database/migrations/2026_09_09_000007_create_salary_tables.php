<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salary: where somebody's pay goes, and what was paid each month.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THIS APPLICATION DOES NOT CALCULATE PAYROLL
 *
 * Decided 2026-08-27. Pay is worked out in Excel today and will come from
 * payroll software over an API later. A record holds three things: the payslip
 * that calculation produced, the net figure that document states, and when the
 * transfer was made. There is no salary structure, no earnings breakdown and no
 * deductions engine, because a second version of somebody else's calculation is
 * a liability rather than a feature — it disagrees with the payslip, and the
 * payslip is what the person was actually paid.
 *
 * `net_minor` IS AN INTEGER OF PAISE
 *
 * Never a float. 0.1 + 0.2 is not 0.3 in binary floating point, and the place
 * that arithmetic is least forgivable is somebody's pay. See App\Support\Money.
 *
 * THE IDENTIFIERS ARE ENCRYPTED AT REST
 *
 * Account number, PAN and Aadhaar are `text` rather than sized columns because
 * the ciphertext is longer than the value, and they are cast through Laravel's
 * `encrypted` cast on the model. A database dump, a backup on somebody's laptop
 * and a read-only reporting replica are all places these end up otherwise, and
 * none of them has a masking layer.
 *
 * They are also the only thing about pay this application is the source of
 * truth for, which is why they live on their own table with their own
 * lifecycle: they are a standing fact about a person, not a monthly one.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_banking', function (Blueprint $table) {
            $table->id();

            // One set of details per person, and restricted: pay history points
            // at the person, and the details are how they were paid.
            $table->foreignId('employee_id')->unique()->constrained()->restrictOnDelete();

            // The bank's name and the branch code are not identifying on their
            // own — "HDFC Bank" is not a secret — so they are plain.
            $table->string('bank_name', 120);
            $table->string('ifsc', 16);

            // These three are. Encrypted at rest; see the head of this file.
            $table->text('account_number');
            $table->text('pan')->nullable();
            $table->text('aadhaar')->nullable();

            $table->timestamps();
        });

        Schema::create('salary_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained()->restrictOnDelete();

            // `YYYY-MM`. A string rather than a date because a period is a
            // month, not a day, and a date column invites somebody to store the
            // 1st and then compare it to the 30th.
            $table->string('period', 7);

            /*
             * The figure the payslip states, in paise, and null until a payslip
             * is added. Null is a real state the payroll page counts: a month
             * somebody has not been given a payslip for is the state where
             * somebody quietly does not get paid.
             */
            $table->unsignedBigInteger('net_minor')->nullable();
            $table->string('currency', 3)->default('INR');

            /*
             * The payslip document. The file itself lives on a private disk and
             * is served only through an authorising, audited route — never from
             * the webroot, where a guessable path is a link somebody can
             * forward.
             */
            $table->string('payslip_path')->nullable();
            $table->string('payslip_name')->nullable();
            $table->unsignedInteger('payslip_bytes')->nullable();
            $table->timestamp('payslip_added_at')->nullable();
            $table->foreignId('payslip_added_by')->nullable()
                ->constrained('employees')->nullOnDelete();

            /*
             * When the transfer was made, and null until it was. Marking paid
             * must be idempotent — running it against an already-paid record
             * must not move this date — which is why it is a nullable date and
             * not a boolean somebody flips.
             */
            $table->date('paid_on')->nullable();
            $table->string('method', 40)->default('Bank transfer');
            $table->foreignId('paid_by')->nullable()
                ->constrained('employees')->nullOnDelete();

            $table->timestamps();

            // One record per person per month. The payroll run is built on this
            // being true, and a duplicate is somebody paid twice.
            $table->unique(['employee_id', 'period']);
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_records');
        Schema::dropIfExists('employee_banking');
    }
};
