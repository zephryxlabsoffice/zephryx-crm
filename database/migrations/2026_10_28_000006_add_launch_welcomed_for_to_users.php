<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The launch-day welcome (owner, 2026-10-28): which launch date each person
 * has been welcomed for. A date rather than a yes/no, so moving the launch
 * date later welcomes everybody again on the new day.
 *
 * Anybody here already has been "launched" if the date on file has passed,
 * so they are marked welcomed for it - nobody gets a launch popup for a
 * launch that happened weeks ago.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('launch_welcomed_for')->nullable();
        });

        $stored = DB::table('company_settings')->where('key', 'attendance.starts_on')->value('value');
        $launch = $stored === null ? null : json_decode($stored, true);

        if (is_string($launch) && $launch <= now()->toDateString()) {
            DB::table('users')->update(['launch_welcomed_for' => $launch]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('launch_welcomed_for');
        });
    }
};
