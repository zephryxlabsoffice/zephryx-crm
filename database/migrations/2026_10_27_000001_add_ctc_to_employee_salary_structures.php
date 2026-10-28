<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The annual CTC, typed by HR as part of the pay agreement (owner, 2026-10-27).
 *
 * TYPED, NEVER DERIVED. The foundation spec keeps this application out of
 * calculating pay, and a CTC worked out from the components would be exactly
 * that - so it is its own figure, stated by whoever agreed it, in minor units
 * like every other amount. Nullable: not every agreement states one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_salary_structures', function (Blueprint $table) {
            $table->bigInteger('ctc_minor')->nullable()->after('rate_basis');
        });
    }

    public function down(): void
    {
        Schema::table('employee_salary_structures', function (Blueprint $table) {
            $table->dropColumn('ctc_minor');
        });
    }
};
