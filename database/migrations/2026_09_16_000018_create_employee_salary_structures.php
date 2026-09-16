<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What somebody is paid, broken into the components a payslip is built from.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THIS IS STILL NOT A PAYROLL ENGINE
 *
 * The 2026-08-27 decision holds: this application does not calculate anybody's
 * pay. HR works the month out in a spreadsheet — LOP, bonus, incentive, arrears
 * and a rejected attendance day all absorbed there — and uploads the payslip
 * that calculation produced. `salary_records` keeps that document and the net
 * figure it states, and nothing here changes it.
 *
 * What this table adds is the STANDING agreement: Basic, HRA, other allowances,
 * PF, PT and TDS, recorded when somebody is hired so the person preparing the
 * payslip has the figures to start from (decided 2026-09-12). It is an input to
 * a calculation that happens elsewhere, not a calculation.
 *
 * Which is why nothing reads it into a monthly figure, and why the Salary page
 * does not show it at all. A breakdown displayed beside a payslip invites the
 * comparison — and the two will disagree in any month with a deduction in it,
 * at which point the screen is arguing with the document somebody was actually
 * paid against.
 *
 * ONE ROW PER PERSON, AND WHAT THE COLUMNS MEAN DEPENDS ON `kind`
 *
 * Three engagements are paid three different ways, and they do not share a
 * shape:
 *
 *   breakdown — full-time. The six components.
 *   stipend   — an intern. One monthly amount, no breakdown, because there is
 *               no PF on a stipend and an HRA line on it would be a fiction.
 *   rate      — a freelancer. A per-project or hourly figure, and nothing else.
 *               Their payments are tracked outside the CRM entirely, so this is
 *               a record of what was agreed rather than anything that is acted
 *               on here.
 *
 * Hence the nullable columns. A stipend row with a null HRA is not an
 * incomplete row — HRA is not a question that applies to it. The alternative,
 * three tables, would make "what is this person paid" a three-way join for a
 * page that shows one card.
 *
 * AMOUNTS ARE INTEGERS OF MINOR UNITS
 *
 * Paise, never rupees, and never a float — see App\Support\Money. Signed
 * BIGINT rather than unsigned, unlike `salary_records.net_minor`: a correction
 * is conceivable here in a way it is not on a figure a payslip states, and an
 * unsigned column turns a mistaken negative into a wildly large positive
 * instead of an error.
 *
 * NOT ENCRYPTED, AND THAT IS A CHOICE
 *
 * `employee_banking` is encrypted because an account number is an instrument:
 * possession of it enables something. A salary figure is confidential, not an
 * instrument, and `salary_records.net_minor` — the figure actually paid, every
 * month, for everybody — has been a plain integer since that table was built.
 * Encrypting the agreement while the payments beside it sit in the clear would
 * buy nothing and suggest a protection that is not there. The control is the
 * permission: `salary.view` to read it, `salary.manage` to write it, and the
 * row is never loaded for anybody else.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_structures', function (Blueprint $table) {
            $table->id();

            /*
             * One per person. Restricted on delete like `employee_banking`, and
             * for the same reason: nothing about pay may be removed by deleting
             * somebody. Records are closed, never deleted (2026-09-12).
             */
            $table->foreignId('employee_id')->unique()->constrained()->restrictOnDelete();

            // breakdown | stipend | rate. Which columns below mean anything.
            $table->string('kind', 20);

            // Stated per row rather than assumed. A freelancer invoicing from
            // outside India is the case this exists for; everybody else is INR.
            $table->string('currency', 3)->default('INR');

            // ── breakdown: a full-time monthly agreement ──
            $table->bigInteger('basic_minor')->nullable();
            $table->bigInteger('hra_minor')->nullable();
            $table->bigInteger('allowances_minor')->nullable();
            $table->bigInteger('pf_minor')->nullable();
            $table->bigInteger('pt_minor')->nullable();
            $table->bigInteger('tds_minor')->nullable();

            // ── stipend: an intern ──
            $table->bigInteger('stipend_minor')->nullable();

            // ── rate: a freelancer ──
            $table->bigInteger('rate_minor')->nullable();
            // 'project' or 'hour'. A rate with no basis is a number nobody can
            // act on, so the two are written together or not at all.
            $table->string('rate_basis', 20)->nullable();

            /*
             * Who last set it. Nullable on delete rather than restricted — a
             * salary must not become unchangeable because the account that last
             * touched it was closed — and the audit log holds the accountable
             * record either way, with the actor's name copied into it.
             */
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_structures');
    }
};
