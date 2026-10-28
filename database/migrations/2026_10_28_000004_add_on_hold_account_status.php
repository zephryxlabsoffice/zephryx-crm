<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * "On hold (security)": where a "This wasn't me" button puts an account
 * (owner, 2026-10-28). Its own status rather than a suspension - see
 * App\Support\Auth\SecurityLock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'suspended', 'on_hold'])
                ->default('active')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'suspended'])
                ->default('active')
                ->change();
        });
    }
};
