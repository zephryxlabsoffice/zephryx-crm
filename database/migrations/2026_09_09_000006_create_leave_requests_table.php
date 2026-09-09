<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave requests (foundation spec §12).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * `days` IS STATED, NOT COMPUTED — AND THAT IS THE MODULE'S WHOLE DESIGN
 *
 * Decided 2026-08-28. This module counts; it does not decide. There is no
 * working-day calculator and no automatic deduction: the requester says how
 * many days it costs and the approver agrees it. Whether a Saturday counts,
 * whether a holiday in the middle of a week is skipped, whether half a day is
 * possible — those are judgements two people make, and encoding a guess at them
 * produces balances that quietly disagree with what people were actually
 * granted.
 *
 * So it is a stored decimal with one place, not a derived integer.
 *
 * THE REASON AND THE CONTACT NUMBER ARE PERSONAL DATA
 *
 * "Fever, seeing a doctor" is health information. It reaches the approver and
 * nobody else — no page listing many people at once shows either column. The
 * schema cannot enforce that; the queries do, and this note is here so nobody
 * adds them to a report by accident.
 *
 * WHAT `cancelled` MEANS, AND WHY IT IS NOT `rejected`
 *
 * They are opposite events. Cancelled is something the requester did to their
 * own request; rejected is something that was done to them. One status column
 * holds both, and every screen keeps them visibly apart.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();

            // LV-2026-041 — the reference in every URL and quoted in email.
            $table->string('reference', 32)->unique();

            // Restricted: somebody's leave history is part of their record.
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();

            /*
             * The policy's key — `casual`, `sick`, `privilege`, `unpaid` — and
             * a plain string rather than an enum on purpose. Leave types are
             * company policy configured in the Admin Panel (§12), so the set
             * changes without a migration; App\Support\LeavePolicy is what
             * validates against the current list, and a type later removed from
             * the policy still renders on the records that used it.
             */
            $table->string('type', 32)->index();

            $table->date('from_date');
            $table->date('to_date');

            // One decimal place: half days are asked for and granted.
            $table->decimal('days', 4, 1);

            $table->text('reason');

            // Where to reach somebody while they are away. Optional, because
            // not every absence needs one.
            $table->string('contact_number', 32)->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])
                ->default('pending')
                ->index();

            /*
             * The decision: who, when, and what they said. A rejection with no
             * note is one the person has to come and ask about — the handover's
             * reject button captured nothing at all.
             *
             * Nullable because a pending request has none, and a withdrawn one
             * never will.
             */
            $table->foreignId('decided_by')->nullable()
                ->constrained('employees')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            /*
             * When it was asked for, kept separately from `created_at`. They
             * are the same value for a request made through this application
             * and would not be for one imported from wherever leave was
             * recorded before it.
             */
            $table->timestamp('applied_at');

            $table->timestamps();

            /*
             * "Who is off between these dates" — asked by the approver's clash
             * panel, by the absence rail, and by Attendance on every single day
             * it draws for every person.
             */
            $table->index(['employee_id', 'status']);
            $table->index(['from_date', 'to_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
