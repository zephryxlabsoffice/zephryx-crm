<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects, the teams on them, and the end-of-day updates written against them
 * (foundation spec §12.1).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * `visibility` ON AN UPDATE IS THE MOST IMPORTANT COLUMN IN THIS FILE
 *
 * End-of-day notes are written by employees, quickly, at the end of a day. That
 * is what makes them useful and it is exactly why they cannot all go to the
 * client: real ones say "holding the migration until the March invoice is
 * settled" and "third brief for the same block". Every one of those is true,
 * worth recording, and disastrous in front of the client it is about.
 *
 * So the column exists and IT DEFAULTS TO INTERNAL — in the database, not in a
 * form, so a row inserted by a seeder, a script or a future import is internal
 * unless it says otherwise. The direction of that default is the whole safety
 * argument: forgetting to set it hides something that should have been shared,
 * which is an annoyance fixed by a click. The opposite default means forgetting
 * publishes something, which is not fixable at all once it has been read.
 *
 * WHY A DEADLINE IS NOT NULLABLE AND `started_on` IS
 *
 * Every page in this module is built around what is late. A project with no
 * deadline has no answer for the column, the KPI, the sort order or the
 * "upcoming" rail — it would be a row that quietly means "ignore me" on five
 * screens. A start date has no such job: it is history, and a project can be
 * agreed before anybody has begun.
 *
 * WHAT IS NOT HERE YET: ATTACHMENTS
 *
 * The demo updates carried files. Storing uploads safely — where they live, who
 * may fetch one, and the audited download route §12 asks for — is one problem
 * to solve once, and profile documents are waiting on the same answer. An
 * update takes text today; the attachment table joins it when that lands,
 * rather than a half-built version of it being embedded here.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            // The human-facing reference, and what every URL carries.
            $table->string('reference', 32)->unique();

            $table->string('name', 160);

            /*
             * Restricted, and required. Work in this application is work for
             * somebody: a project whose client had been removed would be an
             * invoice nobody could explain the origin of. Clients are never
             * deleted anyway — they are marked completed — so the constraint is
             * a statement of intent as much as a guard.
             */
            $table->foreignId('client_id')->constrained()->restrictOnDelete();

            /*
             * Nullable: "Unassigned" is a state the list draws on purpose, and
             * a project can be signed before anybody is put on it.
             */
            $table->foreignId('manager_id')->nullable()
                ->constrained('employees')->nullOnDelete();

            // 0–100. A tiny integer because it is a percentage somebody types,
            // not a computed figure — see the model for why it is not derived
            // from tasks yet.
            $table->unsignedTinyInteger('progress')->default(0);

            $table->enum('status', ['planning', 'in_progress', 'review', 'on_hold', 'completed', 'cancelled'])
                ->default('planning')
                ->index();

            $table->enum('priority', ['high', 'medium', 'low'])->default('medium');

            $table->date('started_on')->nullable();

            // See the head of this file. Indexed because every list in the
            // module sorts or filters on it.
            $table->date('deadline')->index();

            $table->timestamps();

            // "What is this client working on with us" — asked by the client
            // portal on every page it draws.
            $table->index('client_id');
        });

        Schema::create('project_teams', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();

            $table->timestamps();

            // One assignment per team per project: assigning twice must be one
            // row, not two, whatever the form does.
            $table->unique(['project_id', 'team_id']);
        });

        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();

            $table->string('reference', 32)->unique();

            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            /*
             * Who wrote it. Restricted: an update is somebody's account of
             * their day, and it must not lose its author because their record
             * was later closed.
             */
            $table->foreignId('author_id')->constrained('employees')->restrictOnDelete();

            $table->string('title', 160);
            $table->text('body');

            // The column this whole file is about. Internal by default.
            $table->enum('visibility', ['internal', 'client'])->default('internal');

            /*
             * When it first became client-visible.
             *
             * Kept separately from `visibility` because turning visibility back
             * to internal does NOT undo the disclosure: if the client has read
             * it, it has been read. This column remembers that it was once
             * published, so no screen can imply hiding it was a recall.
             */
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            // The project's log, newest first — the one query this table has.
            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_updates');
        Schema::dropIfExists('project_teams');
        Schema::dropIfExists('projects');
    }
};
