<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects get the decided shape (review round, 2026-09-11; built 2026-09-21).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * `progress` GOES — IT IS DERIVED FROM TASKS NOW
 *
 * `Project::class`'s own docblock said this from the day it was built: "The
 * obvious improvement is to derive it from completed tasks. It is not done
 * here because Tasks has no table yet... When Tasks lands this becomes a
 * derived figure and the column goes." Tasks landed
 * (2026_09_09_000004_create_tasks_table.php). This is that promise kept.
 *
 * `ProjectDirectory::row()` computes it now — completed tasks over total
 * tasks for that project, the same place `due_in` is already computed rather
 * than stored, for the same reason: a number somebody typed can disagree with
 * the facts beside it, and a derived one cannot.
 *
 * No backfill needed. Nothing reads the stored value after this migration —
 * every write path (ProjectController::validated, projects/form.blade.php)
 * comes out in the same commit — so there is no data to carry forward, only a
 * column to drop. And no index to fight, unlike the Clients and Teams status
 * migrations: `progress` was never indexed.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('progress');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress')->default(0)->after('manager_id');
        });
    }
};
