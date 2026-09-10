<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a client's invoices should be addressed.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * THE CLIENT PORTAL HAS BEEN ASKING FOR THIS SINCE IT WAS BUILT
 *
 * `client/profile` has had an Address field on its form since the page existed,
 * disabled, above a note saying the write lands with the backend. There has
 * never been a column behind it — the client record carries `notes`, which is
 * ours and is not an address.
 *
 * It is `billing_address` and not `address`, because that is the one thing it
 * is for. A client organisation has a registered office, a site we visit and a
 * place to send invoices, and a column called `address` would collect whichever
 * of the three the person filling the form happened to think of.
 *
 * IT IS THE CLIENT'S TO CHANGE, WHICH ALMOST NOTHING ELSE ON THAT RECORD IS
 *
 * Not the company name — invoices, projects and tickets reference a client by
 * it, and letting the far side rewrite it would rename their own history. Not
 * the industry, not the status. How to reach them and where to bill them is the
 * whole list, and this column is the last part of it that had nowhere to go.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('billing_address')->nullable()->after('contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('billing_address');
        });
    }
};
