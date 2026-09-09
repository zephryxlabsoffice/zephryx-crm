<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance (foundation spec §12).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * ONE PERSON, ONE DATE, TWO TIMES. THERE IS NO STATUS COLUMN.
 *
 * Present, half day and absent are DERIVED from the two times and the policy —
 * see App\Support\AttendancePolicy. Stored, a status is a number that drifts
 * away from the facts underneath it: change the half-day threshold and every
 * historical row would disagree with the rule that produced it, and attendance
 * is exactly the kind of record somebody eventually argues about.
 *
 * AN ABSENCE HAS NO ROW
 *
 * It is the absence of one. Writing "absent" rows would mean deciding at some
 * point in the morning that somebody is not coming, and then deleting the row
 * when they walk in at eleven. The roll asks the calendar who was expected and
 * looks for a record against each of them.
 *
 * THE UNIQUE KEY IS THE IDEMPOTENCY
 *
 * (employee_id, date) is unique, so checking in twice is one row whatever the
 * button does — §12's rule that a double submit must not produce two records is
 * kept by the database and not by the interface hiding a button.
 *
 * NO WRITE EVER CHANGES A TIME
 *
 * Not check-in, not check-out, not a rejection. A wrong record is rejected with
 * a reason and stays legible; editing the times would make the trail a record
 * of the last person to touch it rather than of what happened.
 *
 * WHAT IS NOT HERE: AN `auto_rejected` FLAG
 *
 * A day left open past the ten-hour window reads as rejected because the clock
 * passed it, not because anything stamped it. A stored flag would need a
 * nightly job, and the first night that job failed to run, a hundred days would
 * quietly count as present.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();

            // Restricted: years of somebody's attendance must not disappear
            // with their employment record.
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();

            $table->date('date');

            /*
             * Not nullable. A record exists because somebody checked in — that
             * is the event it records. A row with no check-in would be an
             * absence written down, which is the thing this table deliberately
             * does not do.
             *
             * Stored as a time of day rather than a full timestamp because the
             * date is already a column, and the pages, the policy and the
             * arithmetic all read "09:21" against that date.
             */
            $table->time('check_in');
            $table->time('check_out')->nullable();

            /*
             * A rejection is stamped, attributed and explained — all three, or
             * it is not auditable (§6). A rejection nobody explained is one the
             * person has to come and ask about, and this is their record.
             */
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()
                ->constrained('employees')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            // The idempotency, in the schema. See the head of this file.
            $table->unique(['employee_id', 'date']);

            // The roll reads one date across everybody; the calendar reads one
            // person across a month.
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
