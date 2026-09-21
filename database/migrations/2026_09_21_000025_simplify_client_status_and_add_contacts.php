<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clients get the decided shape (review round, 2026-09-11; built 2026-09-21).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * STATUS BECOMES Active / Inactive — REPLACES THE PROJECT-STYLE STATES
 *
 * `pending` / `review` / `on_hold` mapped to `active` — a client is still
 * being worked with in every one of those states, and none of them said
 * anything the portal or the invoice module actually reads differently.
 * `completed` mapped to `inactive` — the engagement is over, and that is
 * exactly what the new value means: an Ex-Client page and read-only access
 * to old invoices (plan doc, client portal decisions, Q4).
 *
 * COUNTRY AND CURRENCY, PER CLIENT (decided)
 *
 * `currency` is not free text: `App\Models\Client::CURRENCIES` is the closed
 * list of what this company actually bills in, the same reasoning
 * `config/invoices.php`'s payment methods list gives for being closed.
 *
 * `client_contacts` — SEVERAL CONTACTS, ONE LOGIN (decided)
 *
 * The one login still reads `clients.contact_*` — that triple stays the
 * "main" contact, editable by the client themselves exactly as it always
 * was (`Client\ProfileController::EDITABLE`). This table is the OTHERS: a
 * client company with three people worth knowing about, self-served by
 * whoever is signed in, add and remove, from their own portal.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite has no ALTER on an enum column; MySQL's own ENUM does, but
        // the portable way to change the allowed set on both is drop and
        // recreate the column, which is what changing an enum's values
        // actually is under the hood either way.
        Schema::table('clients', function (Blueprint $table) {
            $table->string('status_new', 20)->default('active')->after('status');
        });

        DB::table('clients')->where('status', 'completed')->update(['status_new' => 'inactive']);
        DB::table('clients')->whereIn('status', ['active', 'pending', 'review', 'on_hold'])
            ->update(['status_new' => 'active']);

        /*
         * The original column carries an index of its own (see
         * 2026_09_09_000001_create_clients_table.php), and SQLite refuses to
         * drop a column an index still points at — it errors rebuilding the
         * index rather than silently dropping it with the column. Dropped
         * here, explicitly, before the column that carries it.
         */
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('clients_status_index');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->renameColumn('status_new', 'status');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('clients', function (Blueprint $table) {
            // ISO 3166-1 alpha-2. Free text was rejected (client portal
            // decisions, Q3) for the reason every closed list here is: a
            // country field read back as a filter or a mail-merge has to
            // agree with itself.
            $table->string('country', 2)->nullable()->after('industry');

            // INR default: everything this company has ever billed started
            // there. See Client::CURRENCIES for the closed list.
            $table->string('currency', 3)->default('INR')->after('country');
        });

        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            $table->string('name', 120);
            $table->string('email', 190)->nullable();
            $table->string('phone', 32)->nullable();

            $table->timestamps();

            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['country', 'currency']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('status_old', 20)->default('active')->after('status');
        });

        DB::table('clients')->where('status', 'inactive')->update(['status_old' => 'completed']);
        DB::table('clients')->where('status', 'active')->update(['status_old' => 'active']);

        // Same reason as up(): the index up() put on this column has to go
        // before the column does.
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('clients_status_index');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->renameColumn('status_old', 'status');
        });
    }
};
