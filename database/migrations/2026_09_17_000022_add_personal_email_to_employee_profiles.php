<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The address that is not the sign-in address.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * TWO ADDRESSES, AND ONLY ONE OF THEM IS A CREDENTIAL
 *
 * The owner's add-form list (2026-09-11) asks for a personal email alongside
 * the work one. They are not the same kind of thing at all, which is why this
 * column is here and not on `users`:
 *
 *   WORK EMAIL is `users.email`. It is the sign-in identifier (§4.1), unique
 *   across every account, changed only through the two-token confirmation flow,
 *   and it moves to the new account when an intern is converted.
 *
 *   PERSONAL EMAIL is a way to reach somebody. It signs in to nothing, it is
 *   not unique — a couple working here may share one — and its only jobs are
 *   reaching somebody after they leave, and reaching them when the work account
 *   is the thing that is broken.
 *
 * Putting it on `users` would have made it look like a second credential, and
 * the first person to write `where('email', …)->orWhere('personal_email', …)`
 * into a sign-in path would have made it one.
 *
 * SO IT SITS WITH THE OTHER CONTACT DETAILS
 *
 * `employee_profiles` already holds the phone number, the addresses and the
 * emergency contact for exactly this reason: they are read by one page and no
 * list in the application joins on them. It follows the same rule as the rest
 * of that table — HR types it on the add form, and the person can ask for it to
 * be corrected through the change-request flow.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            // Deliberately NOT unique. See the head of this file.
            $table->string('personal_email', 190)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn('personal_email');
        });
    }
};
