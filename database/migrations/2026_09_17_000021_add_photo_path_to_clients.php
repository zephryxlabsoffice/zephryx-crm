<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client's own picture.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE QUESTION THAT BLOCKED THIS IS GONE (settled 2026-09-17)
 *
 * `Client\ProfileController::photo` refused with a 501 and said why: a client
 * account is an ORGANISATION, so what it would upload is a company logo — and a
 * logo appears on invoices, which made "may a client set the logo on their own
 * invoice" a question nobody had answered.
 *
 * The invoices reversal answered it by removing the question. Invoices are now
 * an uploaded PDF that HR prepares outside the CRM, so nothing this application
 * generates carries a client logo at all. What is left is an avatar on their own
 * portal, which the owner has said they may change from settings like anybody
 * else.
 *
 * SO IT IS `photo_path` AND NOT `logo_path`
 *
 * The name records what it is for. A column called `logo` is one somebody will
 * eventually print on a document, and the whole reason this was refused for
 * three weeks is that nobody decided they could.
 *
 * NOT IN THE WEBROOT, LIKE EVERY OTHER FILE HERE
 *
 * A key into the private disk, served through an authorising route. §6 applies
 * to a client's file exactly as it does to an employee's — see
 * App\Support\Documents\DocumentStore.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('billing_address');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
