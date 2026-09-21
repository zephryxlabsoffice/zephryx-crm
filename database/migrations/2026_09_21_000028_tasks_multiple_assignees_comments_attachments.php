<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tasks get the decided shape (review round, 2026-09-11; built 2026-09-21).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * SEVERAL ASSIGNEES — A PIVOT, NOT A SECOND FOREIGN KEY
 *
 * `assignee_id` was a single nullable foreign key. "A task can go to a team and
 * flow to its members" (decided) means more than one person can be on it at
 * once — two developers pairing on the same piece of work is the ordinary
 * case this is for. `task_assignees` is exactly `team_members`'s shape for
 * exactly the same reason: a JSON list of ids cannot be joined against, cannot
 * carry its own foreign key, and would keep pointing at somebody after their
 * employment record closed.
 *
 * Any task carrying an `assignee_id` gets one row in the new table before the
 * column is dropped, so nobody's current assignment is lost in the swap.
 *
 * THE SAME INDEX LESSON, TWICE OVER
 *
 * `assignee_id` carries both an explicit index (`$table->index('assignee_id')`
 * in 2026_09_09_000004_create_tasks_table.php) and a foreign key constraint.
 * The Clients and Teams status migrations this session already hit "SQLite
 * refuses to drop an indexed column with the index still attached" once each;
 * a foreign key is the same trap wearing a different name. Both are dropped
 * explicitly, in order, before the column.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * COMMENTS AND ATTACHMENTS — STAFF ONLY, SO NEITHER NEEDS AN AUDIENCE COLUMN
 *
 * `ticket_comments` carries a `visibility` column because a ticket has two
 * readerships — staff and the client who raised it. A task has one: nobody
 * outside this realm ever reads a task page, so `task_comments` has no such
 * column to get wrong. The author's name is still copied at write time, for
 * the same reason the audit log and ticket threads do it: a thread whose
 * author's account was later removed must not read "somebody said".
 *
 * `task_attachments` is `document_path`/`document_name`/`document_bytes` per
 * row rather than the single-document columns Invoices carries — a task can
 * hold several files at once, an invoice only ever the one PDF behind it.
 * Storage goes through App\Support\Documents\DocumentStore::put(), which is
 * always Google Drive for an uploaded file (decided 2026-09-16) — nothing
 * here is a local path.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')->constrained()->cascadeOnDelete();

            // Restricted, same as team_members: somebody's task history is
            // part of their record and must not be silently detached by a
            // removed employment record — which in practice never happens.
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();

            $table->timestamps();

            // One row per person per task — the same double-submit guard
            // team_members carries.
            $table->unique(['task_id', 'employee_id']);

            // "What is on my plate" — the query this table exists to answer.
            $table->index('employee_id');
        });

        $now = now();

        DB::table('tasks')
            ->whereNotNull('assignee_id')
            ->get(['id', 'assignee_id'])
            ->each(fn ($task) => DB::table('task_assignees')->insert([
                'task_id' => $task->id,
                'employee_id' => $task->assignee_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['assignee_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['assignee_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('assignee_id');
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();

            // Cascading: a comment means nothing without the task it is on,
            // and nothing points at a comment.
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();

            $table->foreignId('author_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('author_label', 120);

            $table->text('body');

            $table->timestamps();

            $table->index('task_id');
        });

        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')->constrained()->cascadeOnDelete();

            $table->foreignId('uploaded_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('uploaded_by_label', 120);

            $table->string('document_path');
            $table->string('document_name', 190);
            $table->unsignedBigInteger('document_bytes');

            $table->timestamps();

            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_attachments');
        Schema::dropIfExists('task_comments');

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('assignee_id')->nullable()->after('team_id')
                ->constrained('employees')->nullOnDelete();
        });

        $now = now();

        DB::table('task_assignees')
            ->orderBy('id')
            ->get(['task_id', 'employee_id'])
            ->groupBy('task_id')
            ->each(fn ($rows, $taskId) => DB::table('tasks')
                ->where('id', $taskId)
                // Only one seat on the way back down. The first person
                // assigned is as good a choice as any — down() is a rollback
                // path, not a fact anybody reads afterwards.
                ->update(['assignee_id' => $rows->first()->employee_id]));

        Schema::table('tasks', function (Blueprint $table) {
            $table->index('assignee_id');
        });

        Schema::dropIfExists('task_assignees');
    }
};
