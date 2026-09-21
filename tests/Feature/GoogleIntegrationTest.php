<?php

namespace Tests\Feature;

use App\Models\GoogleConnection;
use App\Support\Audit\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The Admin Panel's Google connection screen.
 *
 * Three properties carry the weight, matching the plan doc's "Connecting it":
 *
 *   1. The key is write-only — stored encrypted, never rendered back, only
 *      its derived fingerprint and email shown.
 *   2. "Test connection" is real — it goes over HTTP (faked here) and can
 *      fail, and a failure is recorded, not swallowed.
 *   3. Connecting, reconnecting and disconnecting are all audited by
 *      fingerprint, never by key value.
 */
class GoogleIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->signInAsAdmin();
    }

    // fakeServiceAccountKey() and the two fixture keys it uses live on the
    // base Tests\TestCase now — DocumentStoreTest needs them too.

    protected function postWithToken(string $url, array $payload = []): TestResponse
    {
        return $this->withSession(['_token' => 'test-token'])
            ->post($url, $payload + ['_token' => 'test-token']);
    }

    /* ══════════════════════════════════════════════════════════════════════
       CONNECTING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_page_renders_with_nothing_connected(): void
    {
        $body = $this->pageBody('/admin/integrations');

        $this->assertStringContainsString('Not connected', $body);
        $this->assertStringContainsString('No key on file', $body);
    }

    public function test_connecting_stores_the_key_encrypted_and_derives_a_fingerprint(): void
    {
        $key = $this->fakeServiceAccountKey();

        $this->postWithToken('/admin/integrations/connect', [
            'service_account_key' => $key,
            'shared_drive_id' => 'drive-abc123',
        ])->assertRedirect('/admin/integrations');

        $connection = GoogleConnection::current();

        $this->assertTrue($connection->isConnected());
        $this->assertSame('zephryx-crm@test-project.iam.gserviceaccount.com', $connection->service_account_email);
        $this->assertNotNull($connection->key_fingerprint);
        $this->assertSame('drive-abc123', $connection->shared_drive_id);
        $this->assertNotNull($connection->connected_at);

        // At rest, encrypted — the raw column is not the JSON that was posted.
        $raw = DB::table('google_connection')->value('service_account_key');
        $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $raw);
        $this->assertStringNotContainsString('test-project.iam.gserviceaccount.com', $raw);
    }

    public function test_an_invalid_key_is_refused_with_a_clear_reason(): void
    {
        $this->postWithToken('/admin/integrations/connect', [
            'service_account_key' => 'not json at all',
            'shared_drive_id' => 'drive-abc123',
        ])->assertSessionHasErrors('service_account_key');

        $this->assertFalse(GoogleConnection::current()->isConnected());
    }

    public function test_a_key_of_the_wrong_type_is_refused(): void
    {
        // A user's OAuth client secret, say — valid JSON, wrong shape.
        $this->postWithToken('/admin/integrations/connect', [
            'service_account_key' => json_encode(['type' => 'authorized_user']),
            'shared_drive_id' => 'drive-abc123',
        ])->assertSessionHasErrors('service_account_key');
    }

    public function test_the_key_is_never_rendered_back(): void
    {
        $key = $this->fakeServiceAccountKey();
        $decoded = json_decode($key, true);

        $this->postWithToken('/admin/integrations/connect', [
            'service_account_key' => $key,
            'shared_drive_id' => 'drive-abc123',
        ])->assertRedirect();

        $body = $this->pageBody('/admin/integrations');

        $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $body);
        $this->assertStringNotContainsString($decoded['private_key'], $body);

        // What IS shown: the derived, harmless fields.
        $this->assertStringContainsString($decoded['client_email'], $body);
        $this->assertStringContainsString(GoogleConnection::current()->key_fingerprint, $body);
    }

    public function test_connecting_is_audited_and_reconnecting_names_both_fingerprints(): void
    {
        $this->postWithToken('/admin/integrations/connect', [
            'service_account_key' => $this->fakeServiceAccountKey(),
            'shared_drive_id' => 'drive-abc123',
        ])->assertRedirect();

        $first = GoogleConnection::current()->key_fingerprint;

        $connected = DB::table('audit_log')->where('action', AuditLog::GOOGLE_CONNECTED)->latest('id')->first();
        $this->assertNotNull($connected);
        $this->assertStringContainsString($first, $connected->after_json);

        $this->postWithToken('/admin/integrations/connect', [
            'service_account_key' => $this->fakeServiceAccountKey(
                'rotated@test-project.iam.gserviceaccount.com',
                self::TEST_PRIVATE_KEY_ROTATED,
            ),
            'shared_drive_id' => 'drive-abc123',
        ])->assertRedirect();

        $second = GoogleConnection::current()->key_fingerprint;
        $this->assertNotSame($first, $second);

        $reconnected = DB::table('audit_log')->where('action', AuditLog::GOOGLE_RECONNECTED)->latest('id')->first();
        $this->assertNotNull($reconnected);

        // Names the OLD fingerprint in "before" and the NEW one in "after" —
        // never the key itself, in either column.
        $this->assertStringContainsString($first, $reconnected->before_json);
        $this->assertStringContainsString($second, $reconnected->after_json);
    }

    /* ══════════════════════════════════════════════════════════════════════
       TEST CONNECTION — REAL, NOT FAKED SUCCESS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_successful_test_creates_and_deletes_a_file_and_is_audited(): void
    {
        $this->postWithToken('/admin/integrations/connect', [
            'service_account_key' => $this->fakeServiceAccountKey(),
            'shared_drive_id' => 'drive-abc123',
        ])->assertRedirect();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/drive/v3/files*' => Http::sequence()
                ->push(['id' => 'file-xyz'], 200) // the create
                ->push([], 200), // the delete
        ]);

        $this->postWithToken('/admin/integrations/test')
            ->assertRedirect('/admin/integrations');

        Http::assertSentCount(3); // token, create, delete

        $entry = DB::table('audit_log')->where('action', AuditLog::GOOGLE_TEST_SUCCEEDED)->latest('id')->first();
        $this->assertNotNull($entry);
    }

    public function test_a_failed_test_is_shown_and_audited_rather_than_swallowed(): void
    {
        $this->postWithToken('/admin/integrations/connect', [
            'service_account_key' => $this->fakeServiceAccountKey(),
            'shared_drive_id' => 'drive-abc123',
        ])->assertRedirect();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/drive/v3/files*' => Http::response(
                ['error' => ['message' => 'The caller does not have permission']],
                403,
            ),
        ]);

        $this->postWithToken('/admin/integrations/test')->assertRedirect('/admin/integrations');

        // Follow the redirect to read the flashed message off the rendered
        // page rather than parsing the session's internal shape.
        $this->assertStringContainsString(
            'does not have permission',
            $this->pageBody('/admin/integrations'),
        );

        $entry = DB::table('audit_log')->where('action', AuditLog::GOOGLE_TEST_FAILED)->latest('id')->first();
        $this->assertNotNull($entry);
        $this->assertStringContainsString('does not have permission', $entry->after_json);

        // And nothing pretends to have succeeded.
        $this->assertDatabaseMissing('audit_log', ['action' => AuditLog::GOOGLE_TEST_SUCCEEDED]);
    }

    public function test_test_and_disconnect_404_when_nothing_is_connected(): void
    {
        $this->postWithToken('/admin/integrations/test')->assertNotFound();
        $this->postWithToken('/admin/integrations/disconnect')->assertNotFound();
    }

    /* ══════════════════════════════════════════════════════════════════════
       DISCONNECTING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_disconnecting_clears_the_key_but_keeps_the_rest(): void
    {
        $this->postWithToken('/admin/integrations/connect', [
            'service_account_key' => $this->fakeServiceAccountKey(),
            'shared_drive_id' => 'drive-abc123',
            'calendar_id' => 'company-calendar@group.calendar.google.com',
        ])->assertRedirect();

        $fingerprint = GoogleConnection::current()->key_fingerprint;

        $this->postWithToken('/admin/integrations/disconnect')->assertRedirect('/admin/integrations');

        $connection = GoogleConnection::current();

        $this->assertFalse($connection->isConnected());
        $this->assertNull($connection->service_account_email);
        $this->assertNull($connection->key_fingerprint);
        $this->assertNull($connection->connected_at);

        // Not secrets, and not worth retyping to reconnect.
        $this->assertSame('drive-abc123', $connection->shared_drive_id);
        $this->assertSame('company-calendar@group.calendar.google.com', $connection->calendar_id);

        $entry = DB::table('audit_log')->where('action', AuditLog::GOOGLE_DISCONNECTED)->latest('id')->first();
        $this->assertNotNull($entry);
        $this->assertStringContainsString($fingerprint, $entry->before_json);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PERMISSION IS ADMIN_BASE, NOT GRANTABLE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_integrations_permission_is_not_offered_to_any_staff_role(): void
    {
        // Same rule every other admin.* key follows (§2.1) — proven generally
        // by AdminPanelTest::test_admin_permissions_are_not_offered_to_any_role,
        // this asserts the specific key exists and is seeded.
        $this->assertDatabaseHas('permissions', ['permission_key' => 'admin.integrations.view']);

        $this->postWithToken('/admin/access/hr', ['permissions' => ['admin.integrations.view']])
            ->assertSessionHasErrors('permissions.0');
    }
}
