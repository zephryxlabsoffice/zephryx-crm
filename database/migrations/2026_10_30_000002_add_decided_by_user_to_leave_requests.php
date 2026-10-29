<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * v1.2.1 (2026-10-30): who decided a leave request, as an account.
 *
 * `decided_by` is an employee, so a company account (HR Desk) - which has no
 * employment record - approved leave and the request said "Decided by: Nobody
 * yet". This keeps the account that decided, whoever it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreignId('decided_by_user')->nullable()
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by_user');
        });
    }
};
