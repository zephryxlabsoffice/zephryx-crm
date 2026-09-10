<?php

namespace App\Support\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The stored half of the configuration.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * CONFIG IS THE DEFAULT; THIS TABLE IS THE DECISION
 *
 * `apply()` runs once per request, at boot, and pushes every stored row into
 * the config repository. Everything downstream — AttendancePolicy, LeavePolicy,
 * the announcements window, the brand name — keeps calling `config()` and knows
 * nothing about this class.
 *
 * That is deliberate and it is the only design that could be retrofitted
 * safely. Twenty-odd call sites read these values; a version where each one had
 * to remember to ask a settings service instead would be a version where one of
 * them forgot, and the symptom would be a single page still applying last
 * year's working day.
 *
 * IT HAS TO SURVIVE THE TABLE NOT EXISTING
 *
 * `apply()` is called from a service provider, which runs during `migrate`,
 * during `db:wipe`, and on a fresh checkout before any migration has run. A
 * settings loader that threw in those situations would make the migration that
 * creates its own table impossible to run.
 *
 * So a missing table is silence, not an error — this is the one place in the
 * application where swallowing is correct, because the fallback is exactly the
 * behaviour the application had before the table existed.
 * ═════════════════════════════════════════════════════════════════════════════
 */
class CompanySettings
{
    /**
     * Overlay the stored values onto the running configuration.
     */
    public static function apply(): void
    {
        foreach (self::stored() as $key => $value) {
            /*
             * Only keys the catalogue knows about. A row for `app.key` would
             * otherwise be a way to rewrite anything in the configuration from
             * a table — and the catalogue is already the definition of what a
             * setting is, so the check belongs here as well as at the write.
             */
            if (SettingsCatalogue::find($key) === null) {
                continue;
            }

            config([$key => $value]);
        }
    }

    /**
     * Every override, keyed by setting.
     *
     * @return array<string, mixed>
     */
    public static function stored(): array
    {
        try {
            if (! Schema::hasTable('company_settings')) {
                return [];
            }

            return DB::table('company_settings')
                ->pluck('value', 'key')
                ->map(fn (string $value) => json_decode($value, true))
                ->all();
        } catch (Throwable) {
            // No database at all — `artisan` on a fresh checkout, or a
            // misconfigured connection. See the head of this class.
            return [];
        }
    }

    /**
     * Store a value and apply it to the running request.
     *
     * Applied immediately as well as stored, so the redirect that follows a
     * save renders the new value rather than the old one — the page would
     * otherwise show what the person just changed away from, and they would
     * press the button again.
     */
    public static function put(string $key, mixed $value, ?User $actor = null): void
    {
        DB::table('company_settings')->updateOrInsert(
            ['key' => $key],
            [
                'value' => json_encode($value),
                'updated_by' => $actor?->id,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        config([$key => $value]);
    }

    /**
     * Whether a key has ever been changed from its shipped default.
     *
     * The question somebody asks when a number looks wrong, and the reason this
     * table holds overrides only rather than a row per setting.
     */
    public static function isOverridden(string $key): bool
    {
        return array_key_exists($key, self::stored());
    }
}
