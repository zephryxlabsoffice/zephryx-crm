<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * My Profile — the fields a person owns, the files held against them, and the
 * one HR column the header was already drawing.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * WHY `employee_profiles` IS A SECOND TABLE AND NOT ELEVEN MORE COLUMNS
 *
 * Address, emergency contact, languages, skills and the rest are read by one
 * page. The Employees directory does not show them, no other module joins on
 * them, and every list in the application already selects from `employees`.
 * Putting them there would mean the team page, the task assignee dropdown and
 * the payroll run each carrying somebody's home address for no reason — which
 * is a privacy question as much as a width one.
 *
 * One row per employee, created on first save. A person who has never opened
 * the page has no row, and that is not the same as having a row full of nulls:
 * it is the difference between "not stated" and "stated as nothing", and only
 * one of those is worth writing down.
 *
 * THE TWO NOTIFICATION TOGGLES ARE HERE, AND THE THIRD ONE IS NOT
 *
 * `notify_tasks` and `notify_tickets` switch off the two kinds Notifier writes
 * that are high-volume and about work in progress. Leave decisions and meeting
 * invites have no switch: they are how a decision reaches the person waiting
 * for it, and the preferences page says so.
 *
 * The form's third toggle — "also send these by email" — has no column, because
 * nothing in this application emails a notification. A switch that turns on a
 * thing that does not exist is worse than no switch: it is a promise, and the
 * person who set it stops watching the bell.
 *
 * DOCUMENTS ARE ROWS; THE FILES ARE NOT IN THE WEBROOT
 *
 * `path` is a key into the private disk (App\Support\Documents\DocumentStore),
 * never a URL, and there is no column that could hold one. §6 requires the
 * files be unreachable except through a route that has checked who is asking
 * and written an audit entry — a PAN scan at a guessable public path is a link
 * that works for anyone who tries it, forever, with no session involved.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();

            /*
             * Unique: one profile per person. Cascading, unlike almost
             * everything else that points at `employees` — attendance, pay and
             * team history are the company's record and outlive somebody
             * leaving, but their emergency contact is not a record anybody has
             * a reason to keep once the employment is gone.
             */
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('phone', 32)->nullable();
            $table->text('address')->nullable();

            /*
             * Free strings against a list the policy offers, not enums. "Prefer
             * not to say" is a real stored answer on both, and an enum here
             * would make widening the list a migration — which is the wrong
             * cost to attach to somebody asking for an option that describes
             * them.
             */
            $table->string('gender', 40)->nullable();
            $table->string('marital_status', 40)->nullable();
            $table->string('nationality', 60)->nullable();

            // Typed as commas, stored as lists. Nothing queries inside them.
            $table->json('languages')->nullable();
            $table->json('skills')->nullable();

            $table->string('emergency_name', 120)->nullable();
            $table->string('emergency_relationship', 60)->nullable();
            $table->string('emergency_phone', 32)->nullable();

            /*
             * Reserved by this migration and written by the one after it. The
             * column costs nothing here and splitting it out would mean an
             * `employee_profiles` table that the photo commit has to alter.
             */
            $table->string('photo_path')->nullable();

            // Default on: somebody who has never opened this page still gets
            // told when they are given work.
            $table->boolean('notify_tasks')->default(true);
            $table->boolean('notify_tickets')->default(true);

            $table->timestamps();
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();

            // DOC-0011 and the like. What the download route takes, so the
            // primary key is never in a URL.
            $table->string('reference', 32)->unique();

            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // The name it was uploaded with. Data, displayed — never used to
            // compose the path. See DocumentStore.
            $table->string('name', 190);

            // resume, identity, employment. A string against a short list; the
            // list drives an icon and a tone and nothing points at it.
            $table->string('kind', 32);

            // The key into the private disk. Not a URL, and never rendered.
            $table->string('path');

            $table->unsignedBigInteger('bytes');
            $table->string('mime', 120)->nullable();

            /*
             * Who put it there. Nullable on delete rather than restricted: a
             * document is the employee's, and it must not become undeletable
             * account-wise because HR uploaded it. The row keeps `uploaded_by`
             * null and the page reads that as "added by HR" versus "by you"
             * through the comparison, not the name.
             */
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // "This person's documents, newest first" — the only query.
            $table->index(['employee_id', 'id']);
        });

        /*
         * The reporting line, which the profile header has been drawing since
         * the page was built and no table has held.
         *
         * Self-referencing and nullable: the owner reports to nobody, and a new
         * person may not have been placed under anybody yet. Null on delete
         * rather than restricted, so closing a manager's record does not lock
         * every report's row — it leaves them reporting to nobody, which is
         * true and visible, rather than to a record that is gone.
         */
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('reports_to')->nullable()->after('designation_id')
                ->constrained('employees')->nullOnDelete();
        });

        /*
         * Changing the sign-in address (§4.1). Not a column on `users`, because
         * a change in flight is a thing with a state:
         *
         *   - the account keeps signing in with the OLD address until the new
         *     one is confirmed, so both have to exist at once;
         *   - both addresses have to confirm, so there are two tokens and two
         *     timestamps, not one;
         *   - it expires, and it can be abandoned.
         *
         * None of that fits in `users.email`, and the version that tries is the
         * one that writes the new address in immediately and locks somebody out
         * of a system that no longer knows how to reach them.
         */
        Schema::create('email_changes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('new_email');

            /*
             * Two tokens, stored hashed, exactly as password_resets does. The
             * old address proves the person asking is the one who holds the
             * account; the new address proves it is one they can receive at.
             * Either alone is a takeover: the first lets somebody point the
             * account at an address they cannot read, the second lets anybody
             * with a borrowed session move it to their own.
             */
            $table->string('old_token_hash')->index();
            $table->string('new_token_hash')->index();

            $table->timestamp('old_confirmed_at')->nullable();
            $table->timestamp('new_confirmed_at')->nullable();

            $table->timestamp('expires_at')->index();

            // Set when the change is applied, and when it is abandoned. A row
            // with either set is spent; nothing is deleted, because "somebody
            // tried to move this account to that address" is worth keeping.
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->string('ip_address', 45)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_changes');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['reports_to']);
            $table->dropColumn('reports_to');
        });

        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_profiles');
    }
};
