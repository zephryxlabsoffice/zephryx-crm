<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * verify.zephryxlabs.in (2026-10-29).
 *
 * - employees.left_on: the date somebody left, shown on the verify page for a
 *   former employee. Set by HR when a record is closed. Backfilled from the
 *   audit entry that closed it, where there is one.
 * - verify_scans: one row per verify page view, from a QR or from the search
 *   box, for the counts on the verify dashboard. The IP is kept only as a
 *   keyed hash - enough to tell repeat views apart, never an address.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('left_on')->nullable();
        });

        Schema::create('verify_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('source', 8);
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        $closed = DB::table('employees')
            ->join('users', 'users.id', '=', 'employees.user_id')
            ->where('users.status', 'inactive')
            ->get(['employees.id', 'users.user_id', 'users.updated_at']);

        foreach ($closed as $row) {
            $at = DB::table('audit_log')
                ->where('action', 'employee.status_changed')
                ->where('entity_id', $row->user_id)
                ->orderByDesc('id')
                ->value('created_at');

            DB::table('employees')->where('id', $row->id)
                ->update(['left_on' => substr((string) ($at ?? $row->updated_at), 0, 10) ?: null]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('verify_scans');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('left_on');
        });
    }
};
