<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tasks (foundation spec §12.1).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * A TASK IS HELD BY A TEAM OR BY A PERSON, AND BOTH ARE REAL STATES
 *
 * `team_id` and `assignee_id` are both nullable, and the combination that
 * matters is a task with a team and nobody on it: that is the Team Lead's
 * queue, and the whole point of `/tasks/team` is resolving it. Making the
 * assignee required would delete the state the page exists for.
 *
 * WHY `created_by` IS NOT A COLUMN
 *
 * The demo timeline said who created a task and who changed it. Those are audit
 * questions, and the audit log already answers them for every module — a
 * `created_by` here would be a second, partial copy of the same fact that could
 * disagree with the log, and it would still not answer "who reassigned this
 * last Tuesday".
 *
 * `description` IS a column, because it is content rather than history. The
 * page rendered a fixed paragraph of lorem about wireframes for every task,
 * which read as real and was the same on all fourteen.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            $table->string('reference', 32)->unique();

            $table->string('name', 160);
            $table->text('description')->nullable();

            /*
             * Nullable and cascading are both deliberate. A task belongs to a
             * project in practice; the page draws "not linked to a project"
             * because standalone work exists. Where there IS a project, the
             * task has no meaning without it, which is what cascade says.
             */
            $table->foreignId('project_id')->nullable()
                ->constrained()->cascadeOnDelete();

            // Restricted: teams are never deleted, and a task pointing at a
            // team that vanished would lose the queue it sits in.
            $table->foreignId('team_id')->nullable()
                ->constrained()->restrictOnDelete();

            /*
             * Null means nobody has picked it up. nullOnDelete rather than
             * restrict: if an employment record ever were removed, an
             * unassigned task is a correct outcome — the work still exists and
             * somebody else has to do it.
             */
            $table->foreignId('assignee_id')->nullable()
                ->constrained('employees')->nullOnDelete();

            $table->enum('status', ['pending', 'in_progress', 'review', 'completed', 'blocked'])
                ->default('pending')
                ->index();

            $table->enum('priority', ['high', 'medium', 'low'])->default('medium');

            /*
             * Required, for the same reason a project's deadline is: every list
             * in this module sorts and counts on what is late, and a task with
             * no date has no answer for the column, the KPI or the order.
             */
            $table->date('due_on')->index();

            /*
             * When it was finished — the fact behind "completed", kept
             * separately from the status so that reopening a task does not
             * erase that it was once delivered, and so a report can ask how
             * long things actually take.
             */
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // "What is on my plate" and "what does this team still hold" — the
            // two queries this table exists to answer.
            $table->index('assignee_id');
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
