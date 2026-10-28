<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * No em dash anywhere on the site; a plain hyphen instead (owner,
 * 2026-10-28). The code was changed in the same commit, but text the seeders
 * and the Notifier already wrote to the live database carries the old
 * character, so this rewrites it in place.
 *
 * Only system-written text. What people typed themselves (announcements,
 * tickets, comments) is theirs and is left alone.
 */
return new class extends Migration
{
    protected const COLUMNS = [
        'permissions' => ['permission_name'],
        'roles' => ['role_name', 'description'],
        'master_data_items' => ['name'],
        'notifications' => ['title', 'body'],
    ];

    public function up(): void
    {
        $dash = "\u{2014}";

        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                DB::table($table)
                    ->where($column, 'like', '%'.$dash.'%')
                    ->update([$column => DB::raw('REPLACE('.$column.", '".$dash."', '-')")]);
            }
        }
    }

    public function down(): void
    {
        // Nothing to restore: the hyphen is the decision.
    }
};
