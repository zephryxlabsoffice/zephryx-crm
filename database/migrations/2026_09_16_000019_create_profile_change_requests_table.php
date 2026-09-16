<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asking for your own details to be corrected.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * A REVERSAL, AT THE OWNER'S INSTRUCTION (2026-09-14)
 *
 * The 2026-09-03 decision put a long list of fields under the person themselves
 * and defended it in one sentence: "nobody should raise a ticket to correct
 * their own phone number." The owner has reversed it deliberately. The profile
 * is the COMPANY'S record of a person; it is corrected against documents
 * submitted to the office, not on the strength of a form somebody filled in.
 *
 * So the form no longer saves. It REQUESTS: a row lands here, the live record
 * is untouched, the person brings the paperwork in, and HR applies it. Nothing
 * moves on assertion.
 *
 * THE SHAPE IS `email_changes`, AND THAT IS THE POINT
 *
 * That table already solved this problem for the sign-in address: a change in
 * flight is a THING WITH A STATE, and the version that tries to live in the
 * record it is changing is the version that writes the new value in
 * immediately. Same here — pending row, live record untouched, spent rows kept
 * forever rather than deleted, because "somebody asked for this and it was
 * declined" is exactly the history an argument six months later turns on.
 *
 * ONE JSON COLUMN, NOT ELEVEN NULLABLE ONES
 *
 * A request carries only the fields that actually differ from the record — HR
 * reads a list of changes, not eleven rows of which nine say "unchanged". A
 * column per field would make the common request a row of nulls, and would mean
 * a migration every time the requestable list grows. Nothing queries inside it:
 * the queue asks who and when, and the values are read one request at a time.
 *
 * The keys are checked against App\Support\ProfilePolicy::requestable() on the
 * way in AND on the way out. Storing a field name in a column and later writing
 * to the column it names is exactly the shape that becomes a write-anything
 * primitive when the list is trusted — the same reasoning as the reveal
 * endpoint's fixed field list.
 *
 * THE PHOTO IS A FILE, SO IT CANNOT LIVE IN THE JSON
 *
 * A requested photo is stored on the private disk immediately, under its own
 * folder, and this row holds the path. The LIVE photo is untouched until the
 * request is applied. A declined or withdrawn request deletes the candidate
 * file — it is the one part of a spent request that is not kept, because a
 * photograph nobody accepted is not a record of anything and keeping it would
 * grow the disk forever.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_change_requests', function (Blueprint $table) {
            $table->id();

            /*
             * Cascading, like `employee_profiles` itself: these are the fields
             * that table holds, and a request to change them has no meaning
             * once the employment record it belongs to is gone.
             */
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            /*
             * Who asked. Normally the person themselves — this form is only on
             * their own profile — but stored rather than assumed, because the
             * audit question is "who submitted this", and inferring it from
             * `employee_id` would answer a different one.
             */
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();

            // { field => proposed value }, only for fields that differ.
            $table->json('changes');

            // A candidate photo on the private disk. Never a URL; see the head.
            $table->string('photo_path')->nullable();

            /*
             * The three ways a request stops being live, each its own column
             * rather than one `status` string. The dates are the record — when
             * HR applied it, when it was declined — and a status column plus a
             * single `decided_at` cannot say which of the two a date belongs
             * to without reading the other column anyway.
             */
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();

            /*
             * Why HR declined. Required of a decline by the controller and not
             * by the column, because an applied request has no note and a
             * NOT NULL here would mean storing an empty string for every one of
             * them. A decline with no reason is a person told no by a screen.
             */
            $table->string('decision_note', 300)->nullable();

            $table->timestamps();

            /*
             * The queue's only query: live requests, oldest first. Live is
             * "none of the three stamps set", which no index can express — so
             * this covers the employee lookup and the ordering, and the queue
             * filters the three nulls on top. At the size this table will ever
             * reach that is the right trade.
             */
            $table->index(['employee_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_change_requests');
    }
};
