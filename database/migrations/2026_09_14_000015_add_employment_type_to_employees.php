<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What kind of engagement somebody is on.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THIS IS NOT COSMETIC, AND IT IS NOT THE STAFF ID EITHER
 *
 * Decided 2026-09-11. Three things read this column and behave differently:
 * the staff ID carries it as a digit, attendance and leave apply to full-time
 * and interns but not to freelancers at all, and which fields are required when
 * somebody is added depends on it.
 *
 * The identifier ALSO carries the type, which makes it look like the type could
 * be read back out of the string. It cannot, and nothing may: an intern who is
 * converted to full-time is issued a new identifier, but a record whose type
 * changed for any other reason would keep the old one. The column is the truth;
 * the digit is a label that was true on the day it was issued.
 *
 * WHY EVERY EXISTING ROW BECOMES FULL-TIME
 *
 * There were no interns or freelancers in the system when this landed — the
 * distinction did not exist to be recorded — so `full_time` is not a guess
 * about those rows, it is what all of them are.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->enum('employment_type', ['full_time', 'intern', 'freelance'])
                ->default('full_time')
                ->after('designation_id');

            // The directory filters on it, and the required-fields rules and
            // the leave and attendance queries all narrow by it.
            $table->index('employment_type');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['employment_type']);
            $table->dropColumn('employment_type');
        });
    }
};
