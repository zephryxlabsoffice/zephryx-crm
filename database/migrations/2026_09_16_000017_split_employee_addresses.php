<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One address becomes two, because they answer different questions.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WHY TWO (decided 2026-09-12)
 *
 * CURRENT is where somebody lives now: where a courier goes, and the one that
 * changes when they move. PERMANENT is the address on their ID proof — the
 * document HR checked them against — and it is the one that has to match the
 * paper in the file. Held as one column they overwrite each other, and the
 * record stops being a record of anything: after the first move, nobody can
 * tell whether the stored line is the flat they are in or the address the
 * passport says.
 *
 * A RENAME FOR THE FIRST, A NEW COLUMN FOR THE SECOND
 *
 * Everything already typed into `address` was typed under a field labelled
 * "Address" on My Profile, which is where somebody lives. That is the current
 * one, so it is renamed rather than copied — the values move untouched and
 * nothing has to be guessed at. `permanent_address` starts null for everybody:
 * null here means "not stated", which is the state HR has to be able to chase,
 * and is not the same as somebody saying it is the same as the current one.
 *
 * THEY STAY ON `employee_profiles`
 *
 * Not on `employees`, for the reason that table was split in the first place:
 * the team page, the assignee dropdown and the payroll run all select from
 * `employees`, and none of them has any business carrying somebody's home
 * address along for the ride.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->renameColumn('address', 'current_address');
        });

        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->text('permanent_address')->nullable()->after('current_address');
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn('permanent_address');
        });

        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->renameColumn('current_address', 'address');
        });
    }
};
