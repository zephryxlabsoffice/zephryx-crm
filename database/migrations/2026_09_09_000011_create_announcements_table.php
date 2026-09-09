<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The announcements board.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * ONLY AUTHORED POSTS ARE ROWS
 *
 * Birthdays and work anniversaries are on the same board and are NOT in this
 * table. They are computed from employee records on every request by
 * App\Support\Milestones, because a stored birthday post would be wrong the
 * following year and would survive somebody opting out of having theirs
 * announced. There is nothing to schedule, edit or delete about a milestone.
 *
 * A HOLIDAY HAS TWO DATE RANGES AND THEY ARE NOT THE SAME
 *
 * `starts_on`/`ends_on` is how long the NOTICE stays on the board — a week's
 * warning about one Friday. `observed_from`/`observed_to` is when the OFFICE IS
 * SHUT, and it is what Attendance reads. Confusing them closes the office for
 * the whole week the notice was up.
 *
 * That is why the observed columns exist here rather than in an admin holiday
 * list: one list cannot disagree with itself. HR posts the notice people read
 * and the same row decides who was not absent — see App\Support\Holidays for
 * what that costs and what pays for it.
 *
 * A DRAFT CLOSES NOTHING
 *
 * `published_at` null is a draft. Somebody writing "office closed for Diwali"
 * while the dates are still being confirmed must not have already changed
 * everybody's attendance record, so Holidays reads published rows only.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();

            $table->string('reference', 32)->unique();

            $table->string('title', 200);
            $table->text('body');

            /*
             * The board's own categories — holiday, policy, hr, training and
             * the rest. A string against config rather than a table for the
             * same reason ticket categories are: nothing points at one.
             */
            $table->string('category', 32)->index();

            /*
             * Who wrote it. Restricted: a notice people acted on must not lose
             * its author, and a board post signed by nobody is one people
             * quietly stop trusting.
             */
            $table->foreignId('author_id')->constrained('employees')->restrictOnDelete();

            /*
             * Who it is for. `everyone`, or one department — the value is the
             * master data row when it is narrowed, and null when it is not.
             */
            $table->string('audience', 32)->default('everyone');
            $table->foreignId('audience_department_id')->nullable()
                ->constrained('master_data_items')->nullOnDelete();

            // How long the notice is up. `ends_on` null never expires.
            $table->date('starts_on');
            $table->date('ends_on')->nullable();

            /*
             * The days the office is shut, on a holiday notice. Null on every
             * other kind — and null on a holiday notice that is a reminder
             * rather than a closure, which Holidays skips rather than guesses
             * at.
             */
            $table->date('observed_from')->nullable();
            $table->date('observed_to')->nullable();

            // Null is a draft. See the head of this file.
            $table->timestamp('published_at')->nullable();

            $table->boolean('pinned')->default(false);

            $table->timestamps();

            // "What is on the board today" — the one query this table has.
            $table->index(['published_at', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
