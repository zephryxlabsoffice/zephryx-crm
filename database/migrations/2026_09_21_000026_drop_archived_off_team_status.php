<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Teams get the decided shape (review round, 2026-09-11; built 2026-09-21).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * "ARCHIVED" GOES — ACTIVE / INACTIVE ONLY
 *
 * The original three states never earned their keep: nothing in this
 * application reads `archived` differently from `inactive` — both mean "not
 * currently used, keep everything it holds" (§ the head of
 * 2026_09_09_000002_create_teams_tables.php). Two words for one meaning is a
 * filter somebody has to remember has two spellings, so any existing
 * `archived` row folds into `inactive` here rather than surviving as a state
 * nothing distinguishes.
 *
 * THE SAME PORTABLE SWAP AS THE CLIENTS MIGRATION, AND THE SAME INDEX TRAP
 *
 * `status` was created with `->index()`
 * (2026_09_09_000002_create_teams_tables.php), and SQLite refuses to drop an
 * indexed column with the index still attached — `2026_09_21_000025_simplify_
 * client_status_and_add_contacts.php` hit exactly this and had to drop
 * `clients_status_index` first. Done here from the start rather than
 * rediscovered the same way.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('status_new', 20)->default('active')->after('status');
        });

        DB::table('teams')->where('status', 'archived')->update(['status_new' => 'inactive']);
        DB::table('teams')->whereIn('status', ['active', 'inactive'])
            ->update(['status_new' => DB::raw('status')]);

        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex('teams_status_index');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->renameColumn('status_new', 'status');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropIndex('teams_status_index');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->string('status_old', 20)->default('active')->after('status');
        });

        DB::table('teams')->update(['status_old' => DB::raw('status')]);

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->renameColumn('status_old', 'status');
        });
    }
};
