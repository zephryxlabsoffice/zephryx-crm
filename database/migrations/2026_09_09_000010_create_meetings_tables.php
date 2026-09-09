<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meetings, and who is on them.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE CRM ORGANISES; GOOGLE HOSTS
 *
 * Decided 2026-08-28. This table holds who is meeting whom about what, and a
 * reference to the Google Calendar event that actually hosts it. It never
 * hosts, records or proxies a call.
 *
 * `event_id` and `join_url` COME BACK FROM GOOGLE. Nothing here composes a Meet
 * URL from an event id or a room name: a link this application invented would
 * be a link to nothing, rendered as though it worked, and somebody would sit in
 * it waiting for a client. A meeting with neither column set has not been
 * created yet, and the pages say so.
 *
 * `join_url` IS EFFECTIVELY A PASSWORD
 *
 * Anybody holding it can join. It is withheld per viewer in PHP — not rendered
 * and hidden with CSS — because whatever reaches the browser has been read by
 * whoever is at it.
 *
 * ATTENDEE RESPONSES ARE GOOGLE'S
 *
 * The `response` column is a CACHE of what Google Calendar says, refreshed by
 * reading it back. Nothing in this application writes an RSVP: a person accepts
 * or declines in their own calendar, and there is deliberately no route, no
 * button and no provider method that sets one.
 *
 * TIMES ARE STORED UTC
 *
 * Every start and end is UTC; App\Support\MeetingPresenter is the only thing
 * that turns one into something a person reads. A meeting is the one record
 * where a timezone mistake means people miss it.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();

            $table->string('reference', 32)->unique();

            $table->string('title', 200);
            $table->text('agenda')->nullable();

            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            /*
             * Who called it. Nullable because a client REQUEST has no organiser
             * until somebody here picks it up — that is the difference between
             * a requested meeting and a scheduled one.
             */
            $table->foreignId('organiser_id')->nullable()
                ->constrained('employees')->nullOnDelete();

            // The client who asked, on a request. Null on a meeting somebody
            // here called.
            $table->foreignId('requested_by_client_id')->nullable()
                ->constrained('clients')->nullOnDelete();

            $table->timestamp('starts_at');
            $table->timestamp('ends_at');

            /*
             * Google's. Both null until the event is created, and neither is
             * ever composed by this application — see the head of this file.
             */
            $table->string('event_id')->nullable();
            $table->text('join_url')->nullable();
            $table->text('html_link')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();

            $table->timestamps();

            // The list is "what is next", every time it is opened.
            $table->index('starts_at');
        });

        Schema::create('meeting_attendees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();

            /*
             * An attendee is an ACCOUNT, staff or client — `users`, not
             * `employees`, because a client has no employment record at all
             * (§2.1) and both kinds of attendee sit in one invite.
             */
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /*
             * Google's answer, cached. `awaiting` is the state of somebody who
             * has not replied, which is different from somebody who declined —
             * and a column that could not tell them apart would make the
             * attendee list useless for the one thing it is read for.
             */
            $table->enum('response', ['accepted', 'declined', 'tentative', 'awaiting'])
                ->default('awaiting');

            $table->timestamps();

            // One invite per person per meeting.
            $table->unique(['meeting_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_attendees');
        Schema::dropIfExists('meetings');
    }
};
