<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tickets — internal operations and client support — and their comments.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * `visibility` ON A COMMENT IS THIS MODULE'S CONTRACT
 *
 * The same column, and the same argument, as a project update: a support thread
 * carries "the client is on an old browser and will not upgrade" alongside "we
 * have reproduced it and are working on a fix". Both are worth recording and
 * one of them is not for the client to read.
 *
 * It defaults to `public` here and NOT to internal, which is the opposite of a
 * project update — deliberately. A ticket comment is a REPLY: the normal case
 * is answering the person who asked, and a thread where the default silently
 * hides the answer is a thread where the client is left waiting for a response
 * that was written days ago. The internal note is the exception here, and the
 * exception is the thing somebody chooses.
 *
 * A CLIENT TICKET CARRIES A CLIENT, AND THAT IS THE OWNERSHIP KEY
 *
 * §6's worst failure in this module is one client opening another's ticket. The
 * column is indexed because every read in the client portal filters on it.
 *
 * ESCALATION IS FLAT
 *
 * Decided 2026-08-27: one shared review queue, not an L1→L2→L3 ladder. So there
 * is a status and a `escalated_by`, and no level column for somebody to have to
 * define the meaning of.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            $table->string('reference', 32)->unique();

            /*
             * Who it is for. `internal` is somebody here asking IT or HR for
             * something; `client` is a company asking us. They share a table
             * because they share a queue, a triage step and a thread — and
             * differ only in who raised it and who may read it.
             */
            $table->enum('type', ['internal', 'client'])->index();

            $table->string('subject', 200);
            $table->text('description');

            // The staff member who raised it. Null on a client ticket.
            $table->foreignId('raised_by')->nullable()
                ->constrained('employees')->nullOnDelete();

            // The client who raised it. Null on an internal one. Indexed: every
            // read in the client portal filters on this (§6).
            $table->foreignId('client_id')->nullable()
                ->constrained()->restrictOnDelete();

            $table->foreignId('project_id')->nullable()
                ->constrained()->nullOnDelete();

            // Null means nobody has picked it up — the triage queue.
            $table->foreignId('assignee_id')->nullable()
                ->constrained('employees')->nullOnDelete();

            $table->enum('status', ['unassigned', 'open', 'in_progress', 'escalated', 'resolved', 'closed'])
                ->default('unassigned')
                ->index();

            /*
             * Null until triage. A priority nobody set is different from a low
             * one, and the queue is sorted on the difference — an untriaged
             * ticket is not a low-priority ticket, it is one nobody has looked
             * at.
             */
            $table->enum('priority', ['high', 'medium', 'low'])->nullable();

            // Set at triage, both null before it. Free text rather than foreign
            // keys: they are labels the support team maintains, and neither has
            // a row anywhere else pointing at it.
            $table->string('category', 60)->nullable();
            $table->string('department', 60)->nullable();

            $table->foreignId('escalated_by')->nullable()
                ->constrained('employees')->nullOnDelete();
            $table->timestamp('escalated_at')->nullable();

            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index('client_id');
            $table->index('assignee_id');
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();

            // Cascading: a comment means nothing without the ticket it is on,
            // and nothing points at a comment.
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();

            /*
             * Exactly one of these is set. A comment is written by somebody
             * here or by somebody at the client, and both are accounts —
             * `users`, not `employees`, because a client has no employment
             * record at all (§2.1).
             */
            $table->foreignId('author_id')->nullable()
                ->constrained('users')->nullOnDelete();

            /*
             * The author's name, copied at write time, for the same reason the
             * audit log denormalises it: a thread whose author's account was
             * later removed must not read "somebody said".
             */
            $table->string('author_label', 120);
            $table->string('author_role', 40);

            $table->text('body');

            // See the head of this file. Public by default, because a reply is
            // the normal case and a hidden answer is a client left waiting.
            $table->enum('visibility', ['public', 'internal'])->default('public');

            $table->timestamps();

            // The thread, oldest first — the one query this table has.
            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('tickets');
    }
};
