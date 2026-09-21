<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Closed is final. A new ticket can reference the previous one, and closes
 * it." (review round, Tickets, decided 2026-09-21).
 *
 * `supersedes_ticket_id` is set once, at creation — the same shape as
 * `converted_task_id` on the ticket-to-task link: a nullable pointer rather
 * than a boolean, so the new ticket's page can link straight back to the one
 * it replaces. Setting it is what closes the OLD ticket
 * (TicketController::store); nothing else in this application writes this
 * column. "Closed is final" itself needs no schema — it is
 * TicketController::triage() refusing to change a closed ticket's status,
 * the same shape LeaveController::decide() already refuses a second decision
 * on a request that has one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('supersedes_ticket_id')->nullable()
                ->after('converted_task_id')
                ->constrained('tickets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supersedes_ticket_id');
        });
    }
};
