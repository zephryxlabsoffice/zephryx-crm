<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "A ticket can be turned into a task" (review round decision, Tasks).
 *
 * A nullable pointer rather than a boolean: `converted_task_id` says WHICH
 * task a ticket became, so the ticket page can link straight to it, the same
 * reason `converted_from_id`/`convertedTo` thread an intern's old and new
 * employment records together. Never cascades and never restricts on the
 * task side — tasks are never deleted (§ every module in this build), so
 * there is no delete for this to react to.
 *
 * The conversion does not close the ticket, escalate it or touch anything
 * else on it. "Closed is final" and the rest of the Tickets module's own
 * decisions are a later step; this is only the link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('converted_task_id')->nullable()
                ->after('resolved_at')
                ->constrained('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_task_id');
        });
    }
};
