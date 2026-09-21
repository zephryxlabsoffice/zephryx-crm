<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sunday/holiday rostering and comp-off (review round, Attendance: comp-off;
 * built 2026-09-21).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * ROSTERING IS AN ACT, NOT A REQUEST — AND THE TABLE SAYS SO
 *
 * "Sunday work is rostered by a Manager or Team Lead, not requested. No
 * approval step." `sunday_rosters` has no status column for exactly that
 * reason: a row existing IS the roster. `rostered_by` names who put the
 * person on it, for the same reason every other "who decided this" column in
 * this application exists — not to gate anything, only to answer the
 * question afterwards.
 *
 * ONE ROW PER PERSON PER DATE, WHATEVER PUT THEM THERE
 *
 * A Sunday-against-leave approval also produces a row here (see
 * `sunday_against_leave_requests` below) — from that point on, the person IS
 * rostered for that date, and AttendancePolicy::evaluate() cannot tell the
 * two paths apart on the day itself, on purpose: a full day worked earns a
 * comp-off the same way whichever door the rostering came through.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * `comp_offs` IS A LEDGER, AND "LAPSED" IS DERIVED, NOT STAMPED
 *
 * Same reasoning as AttendancePolicy::autoRejected: a comp-off past
 * `expires_on` reads as lapsed because the clock passed it, not because
 * anything wrote a flag — there is no job that could fail to run. The
 * `status` enum therefore has no `lapsed` value; a reader compares
 * `expires_on` against today. See App\Support\CompOffPolicy.
 *
 * A comp-off is earned in whole units only. "Half a Sunday earns nothing —
 * only a full day earns one" (the decision that overrides an earlier,
 * looser answer) is why there is no fractional amount to store here at all —
 * every row is worth exactly one day, which is also why `take_date` is a
 * single date rather than a range.
 *
 * WHY THIS TABLE EXISTS AT ALL, RATHER THAN BEING DERIVED LIKE A DAY'S STATE
 *
 * Attendance's day-by-day state is cheap to recompute from two timestamps and
 * the policy every time it is asked. A comp-off is not: it has to survive
 * being earned on one date and spent (or left to lapse) on another, which is
 * exactly the shape a ledger has and a pure function does not. It is written
 * once, at check-out, the moment a rostered day resolves to a full day
 * present — the same "no midnight job" reasoning the rest of Attendance
 * follows, applied to the one place here that needs a row to persist.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * `sunday_against_leave_requests` — A LINK TO THE LEAVE REQUEST IT PARTLY REVERSES
 *
 * "Working a Sunday against leave already taken needs manager approval. The
 * leave day returns to the balance." This module counts what is recorded
 * (LeavePolicy's own rule) rather than fragmenting an approved multi-day
 * request into two ranges: approval reduces `leave_requests.days` by one on
 * the linked request and creates a `sunday_rosters` row for the date, which
 * is the whole of what "returns to the balance" and "may now work it" mean
 * in this schema. Asked BEFORE working the Sunday, per the decision — there
 * is no path that creates the roster row after the fact from this table.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunday_rosters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained()->restrictOnDelete();

            $table->date('date');

            // Who rostered them — a Manager or Team Lead. Nullable and
            // nullOnDelete: the roster entry outlives whoever created it, the
            // same reason every "who decided" column elsewhere does.
            $table->foreignId('rostered_by')->nullable()
                ->constrained('employees')->nullOnDelete();

            $table->timestamps();

            // One roster entry per person per date, whatever double-submitted
            // the form.
            $table->unique(['employee_id', 'date']);

            $table->index('date');
        });

        Schema::create('comp_offs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained()->restrictOnDelete();

            // The rostered Sunday or holiday a full day was worked on. Unique
            // with employee_id: one comp-off per day worked, never two for
            // the same date however the check-out route is called.
            $table->date('earned_on');

            // The following Sunday — see CompOffPolicy::expiresOn(). Stored
            // rather than computed on read because the rule that produced it
            // ("before the next Sunday") is itself company policy and this
            // way a row keeps the date it was actually granted against, even
            // if the policy changes later.
            $table->date('expires_on');

            $table->enum('status', ['available', 'pending', 'taken', 'rejected'])
                ->default('available')
                ->index();

            // Set once a taking request is made, and again once it is
            // decided. Null on an untouched, available row.
            $table->date('take_date')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('decided_by')->nullable()
                ->constrained('employees')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            $table->timestamps();

            $table->unique(['employee_id', 'earned_on']);
            $table->index('employee_id');
        });

        Schema::create('sunday_against_leave_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained()->restrictOnDelete();

            // The approved leave request this partly reverses. Restricted:
            // this row is meaningless once that one is gone, and leave
            // requests are never deleted anyway.
            $table->foreignId('leave_request_id')->constrained()->restrictOnDelete();

            // The Sunday within that leave's range the person wants to work.
            $table->date('date');

            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->index();

            $table->timestamp('requested_at');
            $table->foreignId('decided_by')->nullable()
                ->constrained('employees')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunday_against_leave_requests');
        Schema::dropIfExists('comp_offs');
        Schema::dropIfExists('sunday_rosters');
    }
};
