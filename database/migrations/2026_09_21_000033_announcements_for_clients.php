<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks an announcement visible to clients too.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * A SEPARATE FLAG, NOT A FOURTH `audience` VALUE
 *
 * `audience` (everyone / department / managers) answers "which of OUR staff",
 * and every value in it is a way of narrowing the internal board. A client is
 * not a narrower slice of staff — it is a second, unrelated board this same
 * post can also appear on. Folding it into `audience` would make "everyone"
 * and "clients" mutually exclusive, when a policy notice is routinely both.
 *
 * Decided 2026-09-21, closing the Announcements step of the rework order:
 * "clients" audience marking.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('for_clients')->default(false)->after('audience_department_id');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('for_clients');
        });
    }
};
