<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teams, and who is in them (foundation spec §12.1).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * MEMBERSHIP IS A TABLE, NOT A COLUMN
 *
 * The demo rows carried members as a list on the team. As a database that would
 * be a JSON column holding staff ids — which cannot have a foreign key, cannot
 * be joined against, and would quietly keep pointing at somebody after their
 * record was closed. "Which teams is this person in?" would be a search inside
 * a text field.
 *
 * So membership is `team_members`, one row per person per team, with both ends
 * constrained. It also gives membership somewhere to record `joined_at`, which
 * a list of ids has nowhere to put.
 *
 * THE LEAD IS AN EMPLOYEE, AND SEPARATE FROM MEMBERSHIP
 *
 * `lead_id` names who runs the team; the pivot says who is in it. They overlap
 * in practice and are not the same statement: a lead is normally a member and a
 * team can have members with no lead at all, which is a real state the list
 * shows as "No lead assigned" rather than as a blank.
 *
 * WHY THERE IS NO DELETE ANYWHERE ABOVE THIS
 *
 * Tasks and projects will point at teams. A team that stops being used is
 * marked inactive, which is why `status` has three values and none of them
 * means "gone".
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();

            // The human-facing identifier — TM-1001, and what every URL carries.
            $table->string('reference', 32)->unique();

            $table->string('name', 120)->unique();
            $table->string('purpose', 200)->nullable();

            /*
             * Nullable, because a team with no lead is a real state — one that
             * has just been formed, or one whose lead has moved on. Restricted
             * rather than cascading for the same reason as everywhere else:
             * removing a person must never take a team with them. In practice
             * employees are never deleted at all.
             */
            $table->foreignId('lead_id')->nullable()
                ->constrained('employees')->nullOnDelete();

            $table->enum('status', ['active', 'inactive', 'archived'])
                ->default('active')
                ->index();

            /*
             * When the team was formed, which is not when its row was inserted.
             * `created_at` would say the team was formed the day the database
             * was seeded, and the overview prints this as "Active since".
             */
            $table->date('formed_on')->nullable();

            $table->timestamps();
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();

            // Cascading here and nowhere else: a membership row means nothing
            // once the team it is a membership OF is gone. Nothing points at a
            // membership, so nothing is orphaned by removing one.
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            // Restricted: somebody's team history is part of their record.
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();

            $table->date('joined_at')->nullable();

            $table->timestamps();

            /*
             * One membership per person per team. Enforced here rather than by
             * the form hiding people who are already members: a double submit
             * must produce one row, and "add" is a button somebody clicks twice
             * on a slow connection.
             */
            $table->unique(['team_id', 'employee_id']);

            // "Which teams is this person in?" — asked by the profile page and
            // by every ownership check a Team Lead's permission runs.
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
    }
};
