<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * v1.2.1 (2026-10-30): HR marks a forgotten check-out as present.
 *
 * A day left open past the auto-reject window reads as rejected, and nothing
 * could lift that. Somebody who worked a full day and only forgot to check out
 * lost the day. HR can now mark it present: it counts as attended, shown as
 * "Present (warning)", with no hours and the recorded times untouched.
 *
 * `marked_present_by` is a user, not an employee, so a company account (HR
 * Desk), which has no employment record, can do it and still be named.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->timestamp('marked_present_at')->nullable();
            $table->foreignId('marked_present_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('marked_present_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marked_present_by');
            $table->dropColumn(['marked_present_at', 'marked_present_reason']);
        });
    }
};
