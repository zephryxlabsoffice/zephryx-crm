<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket attachments (review round, Tickets: "Attachments allowed", decided
 * 2026-09-21).
 *
 * Exactly `task_attachments`' shape — see
 * 2026_09_21_000028_tasks_multiple_assignees_comments_attachments.php for the
 * reasoning this repeats rather than re-derives: several files per ticket,
 * `document_path`/`document_name`/`document_bytes` per row, always through
 * DocumentStore::put() to Google Drive, add-only (no delete route, matching
 * every other module this round).
 *
 * Read by both realms this ticket is visible in — the staff queue and, when
 * it is a client ticket, the client portal. Uploading is staff-only for now;
 * see TicketController for the routes and the plan doc for the reasoning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();

            $table->foreignId('uploaded_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('uploaded_by_label', 120);

            $table->string('document_path');
            $table->string('document_name', 190);
            $table->unsignedBigInteger('document_bytes');

            $table->timestamps();

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
    }
};
