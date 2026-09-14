<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aadhaar becomes one answer to "which document", rather than the question.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * A RENAME, NOT A NEW COLUMN AND A COPY (decided 2026-09-12)
 *
 * The values in `aadhaar` are ciphertext produced by the `encrypted` cast. A
 * rename moves them untouched, which is the only version of this that does not
 * require decrypting and re-encrypting every row inside a migration — a thing
 * that fails halfway with half the table readable and half not.
 *
 * WHY THE BACKFILL IS NOT A GUESS
 *
 * Every number already in this column was entered under a field labelled
 * Aadhaar. Setting `id_proof_type = 'aadhaar'` where a number exists records
 * what those rows always were; leaving the type null would make them unreadable
 * — Sensitive::idProof withholds a number whose document it cannot name.
 *
 * WHAT THE COPY COLUMN IS FOR
 *
 * The CRM holds the type and the number. The photocopy itself is submitted to
 * the office on paper (decided 2026-09-12), so `id_proof_copy_received_on`
 * records the day it arrived. Null means nobody has it yet, which is the state
 * HR needs to be able to list.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_banking', function (Blueprint $table) {
            $table->renameColumn('aadhaar', 'id_proof_number');
        });

        Schema::table('employee_banking', function (Blueprint $table) {
            // Short and plain: it is a key from App\Support\IdProof, not free
            // text, and the four it may hold are a rule in code.
            $table->string('id_proof_type', 20)->nullable()->after('pan');

            $table->date('id_proof_copy_received_on')->nullable()->after('id_proof_number');
        });

        DB::table('employee_banking')
            ->whereNotNull('id_proof_number')
            ->update(['id_proof_type' => 'aadhaar']);
    }

    public function down(): void
    {
        Schema::table('employee_banking', function (Blueprint $table) {
            $table->dropColumn(['id_proof_type', 'id_proof_copy_received_on']);
        });

        Schema::table('employee_banking', function (Blueprint $table) {
            $table->renameColumn('id_proof_number', 'aadhaar');
        });
    }
};
