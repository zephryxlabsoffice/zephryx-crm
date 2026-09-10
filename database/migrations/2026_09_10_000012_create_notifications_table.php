<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The bell (§8), and the other half of Announcements.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * EVERY ROW BELONGS TO EXACTLY ONE READER
 *
 * `user_id` is not nullable and there is no audience column. An announcement is
 * addressed to a room; a notification is addressed to a person, and a row with
 * nobody on it is one that either reaches everybody or reaches nobody depending
 * on which query finds it first. Cascading on delete follows from the same
 * thing: unlike an audit entry or a ticket comment, "you were assigned a task"
 * is worth nothing once there is no you.
 *
 * THE LINK IS A ROUTE NAME, NOT A URL
 *
 * §8 lists a single `link` column. It is split here into `link_route` and
 * `link_params` because of the rule already decided on 2026-08-28: a
 * notification only links somewhere that exists, and the check is
 * `Route::has()`. A stored URL cannot be checked — it can only be trusted — so
 * the day a route is renamed the bell fills with links that 404 at the moment
 * somebody acts on them, which is the exact failure that rule was written to
 * prevent. It would also bake the host into the row, so a notification written
 * on one domain would send people to another.
 *
 * NO ICON COLUMN
 *
 * The icon is derived from `kind` by the model. Stored, it would be a second
 * copy of a decision the sidebar already makes, and rows written last year
 * would keep an icon the application no longer draws.
 *
 * NOTHING IS EVER DELETED HERE, AND NOTHING IS EDITED
 *
 * The only column any write touches after insert is `read_at`. There is no
 * "dismiss", because a dismissed notification and a read one are the same thing
 * to everybody except the person who wanted to find it again.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            /*
             * The reader — an ACCOUNT, not an employment record.
             *
             * §8 keys this on `user_id` and that is right for more than
             * consistency: a Mentor and the owner hold no Employee base (§2.1),
             * and both can be an attendee on a meeting that gets cancelled.
             * Keyed on `employees` they would silently receive nothing.
             */
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /*
             * Which module it came out of — task, ticket, meeting, leave. Drives
             * the icon and nothing else; there is no filter on it, because a
             * queue somebody has to slice by category is already too long.
             */
            $table->string('kind', 32);

            $table->string('title', 200);
            $table->string('body', 500);

            // See the head of this file. Null on both means no link at all.
            $table->string('link_route', 100)->nullable();
            $table->json('link_params')->nullable();

            // Null is unread. Set once, by the reader, and never unset.
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            /*
             * The two queries this table has, and it only ever has these two:
             * "my queue, newest first" and "how many of mine are unread".
             * Both are scoped to one reader, which is why `user_id` leads.
             */
            $table->index(['user_id', 'id']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
