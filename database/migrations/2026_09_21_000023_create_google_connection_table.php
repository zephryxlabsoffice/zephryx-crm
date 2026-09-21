<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The one Google connection the company has (plan doc, "Connecting it").
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * A TABLE, NOT A `company_settings` ROW — AND THAT IS THE WHOLE REASON IT EXISTS
 *
 * `company_settings` overrides `config/` at boot, on every request
 * (App\Support\Admin\CompanySettings::apply()). A service-account private key
 * pushed through that path is a key in reach of any stack trace, any
 * `config:show`, any debug page, and any future `dd(config())` in a hurry.
 * This table is never read at boot and never touches `config()` — only
 * App\Support\Google\GoogleAuth reads `service_account_key`, at the point a
 * request to Google is actually made.
 *
 * ONE ROW. `service_account_email`, `key_fingerprint`, `calendar_id`,
 * `impersonate_email` and `shared_drive_id` are not secrets and may be shown
 * back; `service_account_key` is, and App\Http\Controllers\Admin\
 * IntegrationsController never renders it after it is saved — see the class
 * for the reveal-nothing rule the identity card already follows.
 *
 * SUPERSEDES `config/meetings.php`'s `GOOGLE_CALENDAR_ID`,
 * `GOOGLE_SERVICE_ACCOUNT_KEY` and `GOOGLE_IMPERSONATE_EMAIL` — decided
 * 2026-09-16, "the panel screen configures one Google connection rather than
 * two". GoogleMeetProvider reads this table when it is built; it does not
 * read `.env`.
 * ═════════════════════════════════════════════════════════════════════════════
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_connection', function (Blueprint $table) {
            $table->id();

            $table->string('service_account_email')->nullable();

            // At rest, in the column — the `encrypted` cast, same rule as
            // `employee_banking`. Never pushed into config; read at the point
            // of use and nowhere else.
            $table->text('service_account_key')->nullable();

            // A hash of the key material, shown on the screen so one key can
            // be told from another without ever showing the key itself.
            $table->string('key_fingerprint', 80)->nullable();

            $table->string('calendar_id')->nullable();
            $table->string('impersonate_email')->nullable();
            $table->string('shared_drive_id')->nullable();

            $table->timestamp('connected_at')->nullable();
            // nullOnDelete, not restricted: an admin account leaving must not
            // block deleting it, and the connection itself does not stop
            // working because the person who set it up is gone.
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_connection');
    }
};
