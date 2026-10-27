<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Backups\Backups;
use App\Support\Rbac\Rbac;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Database backups (2026-10-27): the dump, the local copies, the Drive copy,
 * and the Admin page.
 */
class BackupsTest extends TestCase
{
    protected string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        // Never write test backups into the real storage folder.
        $this->storage = sys_get_temp_dir().'/zephryx-backups-test-'.uniqid();
        File::ensureDirectoryExists($this->storage.'/app');
        $this->app->useStoragePath($this->storage);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);

        parent::tearDown();
    }

    public function test_a_backup_is_a_gzipped_sql_dump_of_every_table(): void
    {
        DB::table('company_settings')->insert([
            'key' => 'zephryx.brand.name',
            'value' => json_encode("O'Brien & Sons \"Ltd\""),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $status = app(Backups::class)->run();

        $this->assertSame('not connected', $status['drive']);
        $this->assertGreaterThan(40, $status['tables']);

        $sql = gzdecode((string) file_get_contents(Backups::directory().'/'.$status['file']));

        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('INSERT INTO "roles"', $sql);
        // Quotes inside values are escaped, not breaking the statement.
        $this->assertStringContainsString("O''Brien", $sql);
    }

    public function test_only_the_most_recent_local_copies_are_kept(): void
    {
        File::ensureDirectoryExists(Backups::directory());

        for ($i = 1; $i <= Backups::KEEP_LOCAL + 3; $i++) {
            File::put(Backups::directory().sprintf('/zephryx-crm-db-2026-01-%02d-000000.sql.gz', $i), 'old');
        }

        app(Backups::class)->run();

        $files = app(Backups::class)->list();

        $this->assertCount(Backups::KEEP_LOCAL, $files);
        $this->assertStringStartsWith('zephryx-crm-db-'.now()->format('Y-m-d'), $files[0]['name']);
    }

    public function test_a_copy_goes_to_the_shared_drive_and_old_drive_copies_are_trimmed(): void
    {
        $this->connectGoogleDrive();

        $listed = [];
        for ($i = 0; $i < Backups::KEEP_DRIVE + 2; $i++) {
            $listed[] = ['id' => 'f'.$i, 'name' => sprintf('zephryx-crm-db-2026-01-01-%06d.sql.gz', $i), 'createdTime' => '2026-01-01T00:00:00Z'];
        }

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/drive/v3/files?*' => Http::sequence()
                ->push(['files' => []], 200)                 // folder lookup: none
                ->push(['id' => 'folder-id'], 200)           // folder created
                ->push(['id' => 'file-id'], 200)             // file metadata created
                ->push(['files' => $listed], 200),           // folder listing
            'https://www.googleapis.com/upload/drive/v3/files/*' => Http::response(['id' => 'file-id'], 200),
            'https://www.googleapis.com/drive/v3/files/*' => Http::response([], 204),
        ]);

        $status = app(Backups::class)->run();

        $this->assertSame('sent', $status['drive']);

        $deleted = collect(Http::recorded())
            ->filter(fn (array $pair) => $pair[0]->method() === 'DELETE')
            ->map(fn (array $pair) => $pair[0]->url());

        $this->assertCount(2, $deleted);
        $this->assertTrue($deleted->contains(fn (string $url) => str_contains($url, '/files/f'.(Backups::KEEP_DRIVE + 1).'?')));
    }

    public function test_a_drive_failure_still_leaves_the_local_copy(): void
    {
        $this->connectGoogleDrive();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            '*' => Http::response(['error' => ['message' => 'Drive is down']], 500),
        ]);

        $status = app(Backups::class)->run();

        $this->assertStringStartsWith('failed:', $status['drive']);
        $this->assertFileExists(Backups::directory().'/'.$status['file']);
    }

    public function test_the_owner_can_take_and_download_a_backup_and_both_are_audited(): void
    {
        $this->signInAsAdmin();

        $this->get('/admin/backups')->assertOk()->assertSee('No backup has been taken yet');

        $this->post('/admin/backups')->assertRedirect('/admin/backups');

        $file = app(Backups::class)->list()[0]['name'];

        $this->get('/admin/backups')->assertOk()->assertSee($file);

        $this->get('/admin/backups/'.$file)
            ->assertOk()
            ->assertHeader('content-type', 'application/gzip');

        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::BACKUP_TAKEN)->count());
        $this->assertSame(1, DB::table('audit_log')->where('action', AuditLog::BACKUP_DOWNLOADED)->count());
    }

    public function test_the_download_route_serves_nothing_but_backups(): void
    {
        $this->signInAsAdmin();

        File::ensureDirectoryExists(Backups::directory());
        File::put(Backups::directory().'/last.json', '{}');

        $this->get('/admin/backups/last.json')->assertNotFound();
        $this->get('/admin/backups/..%2F..%2F.env')->assertNotFound();
        $this->get('/admin/backups/zephryx-crm-db-2099-01-01-000000.sql.gz')->assertNotFound();
    }

    public function test_staff_cannot_reach_backups(): void
    {
        $user = User::factory()->create(['account_type' => 'staff', 'staff_kind' => 'employee', 'status' => 'active']);
        $user->roles()->sync(Role::whereIn('role_key', ['employee', 'hr', 'ceo'])->pluck('id'));
        app(Rbac::class)->forget($user);
        $this->actingAs($user);

        // The admin realm answers 404 to anybody outside it (RealmGuardTest).
        $this->get('/admin/backups')->assertNotFound();
        $this->post('/admin/backups')->assertNotFound();
    }

    public function test_backup_times_are_shown_in_office_time_not_utc(): void
    {
        app(Backups::class)->run();

        $at = app(Backups::class)->list()[0]['at'];

        $this->assertSame('Asia/Kolkata', $at->getTimezone()->getName());
        $this->assertSame(now()->format('Y-m-d H'), $at->format('Y-m-d H'));
    }

    public function test_the_artisan_command_takes_a_backup(): void
    {
        $this->artisan('backup:database')->assertExitCode(0);

        $this->assertCount(1, app(Backups::class)->list());
    }
}
