<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Email verification (owner, 2026-10-28): a personal email is verified on its
 * own, because it signs in too and can differ from the work email.
 *
 * Backfill: anybody who has ever signed in received an emailed code at their
 * sign-in address, which proves it, so that address is marked verified from
 * their last sign-in - and the personal email with it when it is the same
 * address.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->timestamp('personal_email_verified_at')->nullable()->after('personal_email');
        });

        DB::table('users')
            ->whereNull('email_verified_at')
            ->whereNotNull('last_login_at')
            ->update(['email_verified_at' => DB::raw('last_login_at')]);

        $verified = DB::table('employee_profiles')
            ->join('employees', 'employees.id', '=', 'employee_profiles.employee_id')
            ->join('users', 'users.id', '=', 'employees.user_id')
            ->whereNotNull('users.email_verified_at')
            ->whereNotNull('employee_profiles.personal_email')
            ->whereRaw('LOWER(employee_profiles.personal_email) = LOWER(users.email)')
            ->get(['employee_profiles.id', 'users.email_verified_at']);

        foreach ($verified as $row) {
            DB::table('employee_profiles')->where('id', $row->id)
                ->update(['personal_email_verified_at' => $row->email_verified_at]);
        }
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn('personal_email_verified_at');
        });
    }
};
