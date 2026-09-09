<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The organisations this company works for.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * A CLIENT IS NOT AN ACCOUNT
 *
 * `users` already holds client accounts — the people who sign into the portal.
 * This is the organisation behind them, and the two are different lifecycles: a
 * client exists from the moment somebody signs a contract, whether or not
 * anybody has been given a login, and one organisation can hand the portal to
 * two people without becoming two clients.
 *
 * `users.client_ref` points here, at `reference`. Everything in the portal is
 * scoped through that column (§6), which is why it holds an identifier and not
 * a name: two companies can share a name, and renaming one must not detach its
 * invoices, tickets and projects.
 *
 * The reference is `CLT001`, deliberately NOT the `CLI001` shape a client
 * ACCOUNT carries as its user_id. They are different things, and a support
 * conversation where the same string might mean either is one nobody can
 * resolve over the phone.
 *
 * WHAT IS NOT HERE: `payment` AND `project`
 *
 * The handover's table drew both as columns of a client. Neither is one.
 *
 * Payment is the state of that client's invoices — stored here it would be a
 * field somebody has to remember to change every time an invoice is raised or
 * settled, and it would be wrong the morning one falls overdue. The project is
 * the Projects module's row, and a client with three projects has no single
 * value to put in the column at all.
 *
 * Both are derived once those modules land. Until then the page shows "—"
 * rather than a stored guess — the same decision, for the same reason, as
 * `on_leave` on employees.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            /*
             * The human-facing identifier, and the one in every URL. Separate
             * from the primary key for the same reason `users.user_id` is: an
             * auto-increment id in a URL leaks how many clients exist and how
             * recently one was signed.
             */
            $table->string('reference', 32)->unique();

            /*
             * Unique, because the whole application refers to clients by name on
             * screen and two identical ones on a list are indistinguishable to
             * the person reading it. Not an identifier for all that — see the
             * head of this file.
             */
            $table->string('name', 160)->unique();

            /*
             * Free text rather than a master data list. The four lists in
             * `master_data_items` are the ones the Admin Panel manages and the
             * ones other tables key on; an industry is a label on a client and
             * nothing points at it. Making it a fifth list would put a screen in
             * front of somebody for a value only ever read as a word.
             */
            $table->string('industry', 80)->nullable();

            /*
             * The engagement, not the account. A client can be `completed` and
             * still have people who sign in to read their old invoices, so this
             * says nothing about whether anybody may log in — `users.status`
             * does that, per account.
             */
            $table->enum('status', ['active', 'pending', 'review', 'on_hold', 'completed'])
                ->default('active')
                ->index();

            // Who to talk to. Nullable: a client can be on the books before
            // anybody has been introduced.
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_email', 190)->nullable();
            $table->string('contact_phone', 32)->nullable();

            /*
             * The account manager. Nullable and nullOnDelete rather than
             * restricted: a client must not become undeletable-by-proxy because
             * the person who looked after them left, and an unassigned client is
             * a real state the list should be able to show.
             */
            $table->foreignId('account_manager_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->date('signed_on')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
