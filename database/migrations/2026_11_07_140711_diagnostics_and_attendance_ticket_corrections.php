<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v1.16.0 (owner, 2026-11-07).
 *
 * - `diagnostic_events`: every server error, expired page and refused attendance
 *   action, with a code the person can quote. See App\Support\Diagnostics.
 * - An attendance day corrected for a technical issue points at the ticket the
 *   employee raised about it, and says who corrected it and why.
 * - `tickets.hr`: HR sees and decides Attendance tickets. The Attendance ticket
 *   category.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('diagnostic_events')) {
            Schema::create('diagnostic_events', function (Blueprint $table) {
                $table->id();
                $table->string('code', 16)->unique();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                // error, expired, refused
                $table->string('kind', 16);
                $table->string('method', 8)->nullable();
                $table->string('path', 255)->nullable();
                $table->unsignedSmallInteger('status')->nullable();
                $table->string('message', 1000);
                $table->string('exception', 255)->nullable();
                $table->string('location', 255)->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }

        Schema::table('attendance_records', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_records', 'corrected_ticket_id')) {
                $table->foreignId('corrected_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
                $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('corrected_at')->nullable();
                $table->string('correction_reason', 1000)->nullable();
            }
        });

        if (DB::table('permissions')->exists()) {
            DB::table('permissions')->insertOrIgnore([
                'permission_key' => 'tickets.hr',
                'permission_name' => 'Handle attendance tickets - tickets.hr',
                'module' => 'tickets',
                'is_sensitive' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $hr = DB::table('roles')->where('role_key', 'hr')->value('id');

            if ($hr !== null) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id' => $hr,
                    'permission_id' => DB::table('permissions')->where('permission_key', 'tickets.hr')->value('id'),
                ]);
            }
        }

        if (DB::table('master_data_items')->where('list', 'ticket-categories')->exists()) {
            DB::table('master_data_items')->insertOrIgnore([
                'list' => 'ticket-categories',
                'code' => 'ATTEND',
                'name' => 'Attendance',
                'is_active' => true,
                'sort_order' => (int) DB::table('master_data_items')->where('list', 'ticket-categories')->max('sort_order') + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_events');

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corrected_ticket_id');
            $table->dropConstrainedForeignId('corrected_by');
            $table->dropColumn(['corrected_at', 'correction_reason']);
        });

        DB::table('permissions')->where('permission_key', 'tickets.hr')->delete();
        DB::table('master_data_items')->where('list', 'ticket-categories')->where('code', 'ATTEND')->delete();
    }
};
