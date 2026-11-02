<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * My Devices (owner, 2026-11-02). See App\Support\Auth\UserDevices.
 *
 * `trusted_devices.user_device_id` is what makes signing one device out mean
 * something: without it the browser would keep its device trust, and with a
 * remember-me cookie it would walk straight back in on its next request.
 * Nullable because trust rows from before this release have no device yet; a
 * row is linked the first time its browser is seen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('browser_hash', 64);
            $table->string('device_name', 120);
            $table->string('user_agent', 512)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('location', 190)->nullable();
            // Null means signed out. Matched against the session on every request.
            $table->string('sign_in_token', 64)->nullable();
            $table->timestamp('signed_in_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'browser_hash']);
        });

        Schema::table('trusted_devices', function (Blueprint $table) {
            $table->foreignId('user_device_id')->nullable()->after('user_id')
                ->constrained('user_devices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trusted_devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_device_id');
        });

        Schema::dropIfExists('user_devices');
    }
};
