<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `users` columns foundation spec §8 calls for.
 *
 * Laravel's stock table has name, email and password. Three columns decide
 * everything else about an account and none of them were there:
 *
 *   `account_type`  which realm this account belongs to (§3). Read by the realm
 *                   middleware on every request, before any data.
 *   `staff_kind`    employee or mentor. This is what grants the Employee base
 *                   (§5) — it is not a role, precisely so a role edit cannot
 *                   revoke it, so it has to live on the account itself.
 *   `status`        active, inactive or suspended (§4.2 step 4).
 *
 * `user_id` is the human-facing identifier — EMP002, and the one printed on
 * screens and quoted in support requests. Separate from the primary key on
 * purpose: an auto-increment id leaks how many accounts exist and how recently
 * one was created, and it cannot be reissued to a person whose record is
 * rebuilt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_id', 32)->unique()->after('id');

            /*
             * The realm. Indexed because the realm middleware reads it on every
             * single request in the application, before anything else happens.
             */
            $table->enum('account_type', ['staff', 'client', 'admin'])
                ->default('staff')
                ->index()
                ->after('user_id');

            // Null for clients and the admin account, which have no Employee
            // base and no personal records at all (§2.1).
            $table->enum('staff_kind', ['employee', 'mentor'])->nullable()->after('account_type');

            $table->enum('status', ['active', 'inactive', 'suspended'])
                ->default('active')
                ->index()
                ->after('staff_kind');

            /*
             * The client an account belongs to, for the client realm. Nullable
             * because staff and admin accounts have none.
             *
             * This is the column every ownership check in the client portal
             * resolves through (§6) — the reason DemoClientPortal takes a client
             * as its first argument everywhere is that this is what will supply
             * it.
             */
            $table->string('client_ref')->nullable()->after('status');

            $table->string('theme_preference', 16)->nullable()->after('client_ref');
            $table->timestamp('last_login_at')->nullable()->after('theme_preference');
            $table->timestamp('password_changed_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'user_id', 'account_type', 'staff_kind', 'status', 'client_ref',
                'theme_preference', 'last_login_at', 'password_changed_at',
            ]);
        });
    }
};
