<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authentication and audit (foundation spec §4, §6, §8).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * EVERY SECRET IN HERE IS STORED HASHED, AND NONE OF THEM IS A PASSWORD
 *
 * One-time codes, device-trust tokens, remember-me tokens and reset tokens are
 * all credentials: presenting one is enough to become somebody. A dump of this
 * database must not hand over a single working one, which is why every column
 * below holds a hash and the plaintext exists only in the email or the cookie
 * it was sent in.
 *
 * The consequence to keep in mind when writing queries against these tables:
 * you cannot look a token up by its value. You look up the user, then verify.
 * Anything that indexes plaintext has gone wrong.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * §4.2 step 1 — rate limiting is per identifier AND per IP.
         *
         * Per identifier alone lets one attacker spread attempts across a
         * botnet; per IP alone lets one office lock out its own staff. Both,
         * and the row records which is which.
         *
         * `identifier` is what was TYPED, not a resolved user: a failed attempt
         * against an address that does not exist is exactly the traffic worth
         * counting, and it has no user to attach to.
         */
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->index();
            $table->boolean('succeeded')->default(false);
            // `invalid_credentials`, `inactive`, `throttled`, `otp_failed` — for
            // the audit log and for answering "what actually happened" later.
            // It is never shown to the person attempting (§4.2 step 3).
            $table->string('failure_reason', 64)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        /*
         * §4.3 — six digits, ten minutes, five attempts, single use.
         *
         * `attempts` is on the CODE, not on the session or the IP. Five guesses
         * at a six-digit code is a 1-in-200,000 chance; five guesses per code
         * with unlimited resends would be unbounded, which is why resending
         * invalidates the previous code rather than issuing a second live one.
         */
        Schema::create('two_factor_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        /*
         * §4.3 — a device trusted for seven days skips the code, not the
         * password.
         *
         * User agent and IP are stored so somebody reviewing their own devices
         * can recognise them, and so a revocation list means something. They are
         * NOT part of the check: a device whose IP changed is still the same
         * device, and refusing it would make trust useless on a laptop that
         * moves between home and the office.
         */
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->index();
            $table->string('user_agent', 512)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('trusted_until')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        /*
         * §4.5 — a ROTATING token, and `previous_token_hash` is the whole point.
         *
         * Each use issues a fresh token and retires the old one. If a retired
         * token is ever presented, the token was copied — the real owner has
         * already rotated past it — so the entire chain is revoked as a theft
         * signal rather than the request simply being refused.
         *
         * Never issued to the admin account (§4.5), and it restores a session
         * without ever bypassing the OTP on an untrusted device.
         */
        Schema::create('remember_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->index();
            $table->string('previous_token_hash')->nullable()->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        /*
         * §4.6 — single use, hashed, sixty minutes.
         *
         * Laravel's stock `password_reset_tokens` is keyed by email and has no
         * expiry column or consumption record, so it cannot express any of
         * this. It is dropped below rather than left as a second, weaker table
         * that a future `Password::broker()` call would silently start using.
         */
        Schema::create('password_resets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('password_reset_tokens');

        /*
         * §6 — the audit log: actor, action, entity, before/after, IP, user
         * agent, timestamp.
         *
         * Before and after are JSON because what changed differs per entity,
         * and both are needed: an entry saying a value changed without saying
         * from what to what cannot answer the question anybody arrives with.
         *
         * `actor_user_id` is nullOnDelete and `actor_label` is stored beside it.
         * The record of what happened has to outlive the people in it — an
         * audit entry that reads "somebody changed a permission" because the
         * account was removed is not an audit entry.
         */
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label')->nullable();
            $table->string('actor_type', 16)->nullable();
            $table->string('action')->index();
            $table->string('entity_type', 64)->nullable()->index();
            $table->string('entity_id', 64)->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('password_resets');
        Schema::dropIfExists('remember_tokens');
        Schema::dropIfExists('trusted_devices');
        Schema::dropIfExists('two_factor_codes');
        Schema::dropIfExists('login_attempts');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
