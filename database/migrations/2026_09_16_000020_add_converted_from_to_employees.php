<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The thread from a full-time record back to the intern one it came from.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * A CONVERSION MAKES TWO RECORDS, AND WITHOUT THIS NOTHING JOINS THEM
 *
 * Decided 2026-09-11: converting an intern issues a NEW staff ID and closes the
 * old record rather than editing the one that exists. That is the right shape —
 * the leave balance starts again, the engagement type is set at creation and
 * never edited, and six months of intern attendance stays filed under the
 * identifier it was recorded against.
 *
 * It also means that, with nothing else, the two records are strangers. Somebody
 * looking at ZEPH261007 has no way to find the ZEPH262004 the person spent
 * their first year under, and "how long have they been here" becomes a question
 * the database cannot answer — which is exactly the sort of thing that is asked
 * when a work anniversary, a notice period or a reference is involved.
 *
 * So one nullable self-reference, set on the new row at the moment of
 * conversion. It points BACKWARDS, from the new record to the old one, because
 * that is the direction the question is asked in: you are looking at somebody's
 * current record and want their history, not the other way round.
 *
 * NULL ON DELETE, LIKE `reports_to`
 *
 * Restricted would make the old record undeletable through the new one, and
 * nothing in this application deletes an employee anyway. If it ever happened,
 * a full-time record whose intern history is gone should lose the pointer, not
 * refuse to exist.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('converted_from_id')->nullable()->after('employment_type')
                ->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['converted_from_id']);
            $table->dropColumn('converted_from_id');
        });
    }
};
