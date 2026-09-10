<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company settings (§8).
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * A DEPLOYED APPLICATION CANNOT EDIT ITS OWN SOURCE
 *
 * Every value the Admin Panel offers comes out of `config/` today, which is
 * fine for a default and impossible as a store: writing to a config file needs
 * the source tree to be writable, and a value that lives in a file is a value
 * the next deploy silently reverts. Somebody changes the working day, ships a
 * fix a week later, and the working day quietly goes back.
 *
 * So the table is the store and config is the DEFAULT. A key with no row here
 * reads whatever `config/` says, which is what makes this migration additive:
 * nothing has to be seeded for the application to keep behaving as it does now.
 *
 * ONLY OVERRIDES ARE ROWS
 *
 * There is no row per setting, seeded on install. A settings table pre-filled
 * with the defaults cannot answer "has anybody ever changed this", and that is
 * the question somebody asks when a number looks wrong. A row here means a
 * person made a decision, and `updated_by` says who.
 *
 * THE VALUE IS JSON BECAUSE A SETTING IS NOT ALWAYS A SCALAR
 *
 * `attendance.week_off` is a list of weekday numbers, and the policy compares
 * with a strict `in_array` — so a column that flattened it to "0,6" would need
 * parsing at every read, and the day somebody parsed it as strings the weekly
 * off would silently match nothing. Storing the shape means the value that
 * comes out is the value that went in.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();

            // The config key, verbatim: `attendance.half_day_hours`. The
            // catalogue is the list of which ones may appear here.
            $table->string('key', 120)->unique();

            $table->json('value');

            /*
             * Who changed it. Nullable on delete rather than restricted — a
             * setting must not become unchangeable because the account that
             * last touched it was removed — and the audit log holds the
             * accountable record either way, with the actor's name copied in.
             */
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
