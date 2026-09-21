<?php

namespace Tests;

use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeBanking;
use App\Models\GoogleConnection;
use App\Models\LeaveRequest;
use App\Models\Project;
use App\Models\Role;
use App\Models\SalaryRecord;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\Google\GoogleServiceAccountKey;
use App\Support\Realm;
use Database\Seeders\AccountSeeder;
use Database\Seeders\AnnouncementSeeder;
use Database\Seeders\AttendanceSeeder;
use Database\Seeders\ClientSeeder;
use Database\Seeders\EmployeeSeeder;
use Database\Seeders\InvoiceSeeder;
use Database\Seeders\LeaveSeeder;
use Database\Seeders\MeetingSeeder;
use Database\Seeders\NotificationSeeder;
use Database\Seeders\ProfileSeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\SalarySeeder;
use Database\Seeders\TaskSeeder;
use Database\Seeders\TeamSeeder;
use Database\Seeders\TicketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /*
     * The schema and the RBAC seed, for every test.
     *
     * Needed since the engine landed: a page's sidebar, its widgets and its
     * route guard are all answered from `roles`, `permissions` and `user_roles`
     * now, so a test without them is a test of an account that holds nothing.
     *
     * `$seed` runs DatabaseSeeder, which is idempotent and cheap at the test
     * suite's BCRYPT_ROUNDS=4.
     */
    use RefreshDatabase;

    protected $seed = true;

    /**
     * Sign in as a staff account holding the given roles.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * EVERY PAGE TEST NOW SIGNS IN, AND THAT IS THE POINT.
     *
     * Before realm middleware existed these tests just asked for a URL. They
     * passed because nothing was guarded — which is exactly the state §3.1
     * describes as unsafe, and exactly why the guard is now on the route group
     * rather than on individual routes.
     *
     * The default is a CEO, because most page tests are about what a page
     * renders rather than about who may see it. Tests that are about the guard
     * pass a narrower role, or none at all.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @param  list<string>  $roles
     */
    protected function signInAsStaff(array $roles = ['ceo'], string $status = 'active'): User
    {
        $user = User::factory()->create([
            'user_id' => 'EMP-T'.fake()->unique()->numberBetween(100, 999),
            'account_type' => Realm::STAFF,
            // The Employee base comes from this column, not from a role (§5).
            'staff_kind' => 'employee',
            'status' => $status,
        ]);

        $user->roles()->sync(Role::whereIn('role_key', $roles)->pluck('id'));

        return tap($user, fn (User $u) => $this->actingAs($u));
    }

    /**
     * Sign in as a mentor — staff, and deliberately without an Employee base
     * (§2.1). The one account type where that column does the whole job.
     */
    protected function signInAsMentor(): User
    {
        $user = User::factory()->create([
            'user_id' => 'MEN-T'.fake()->unique()->numberBetween(100, 999),
            'account_type' => Realm::STAFF,
            'staff_kind' => 'mentor',
            'status' => 'active',
        ]);

        $user->roles()->sync(Role::where('role_key', 'mentor')->pluck('id'));

        return tap($user, fn (User $u) => $this->actingAs($u));
    }

    /**
     * Sign in as a client.
     *
     * Takes the client's NAME, because that is what a test asserting on the
     * portal reads on the page — and creates the `clients` row behind it, since
     * `client_ref` holds a reference now and an account pointing at a client
     * that does not exist is a session scoped to nothing.
     */
    protected function signInAsClient(string $client = 'DGL International School'): User
    {
        $record = Client::firstOrCreate(
            ['name' => $client],
            ['reference' => 'CLT-T'.fake()->unique()->numberBetween(100, 999), 'status' => 'active'],
        );

        $user = User::factory()->create([
            'user_id' => 'CLI-T'.fake()->unique()->numberBetween(100, 999),
            'account_type' => Realm::CLIENT,
            'staff_kind' => null,
            'status' => 'active',
            'client_ref' => $record->reference,
        ]);

        return tap($user, fn (User $u) => $this->actingAs($u));
    }

    /**
     * Sign in as the owner. Holds no roles — the Admin Panel's capabilities are
     * implicit in the account type, because §2.1 makes it "not a role and not
     * assignable" (see Rbac::ADMIN_BASE).
     */
    protected function signInAsAdmin(): User
    {
        $user = User::factory()->create([
            'user_id' => 'ADM-T'.fake()->unique()->numberBetween(100, 999),
            'account_type' => Realm::ADMIN,
            'staff_kind' => null,
            'status' => 'active',
        ]);

        return tap($user, fn (User $u) => $this->actingAs($u));
    }

    /**
     * The twelve demo people, as real rows in the database.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * WHY THIS REPLACED `withDemoData()` IN THE MODULES THAT HAVE TABLES
     *
     * Page tests used to make content appear by flipping the environment to
     * local + debug, because the Demo* sources gate on exactly that. Once a
     * module reads from the database, that flip does nothing: there is no row
     * to find, and a test that "passes" against an empty page is asserting the
     * empty state while claiming to assert the list.
     *
     * So the demo people are seeded properly here — accounts by AccountSeeder,
     * employment by EmployeeSeeder, the same two that populate a developer's
     * machine. The environment flip is still needed because both seeders
     * deliberately do nothing outside local + debug.
     * ─────────────────────────────────────────────────────────────────────────
     */
    protected function seedDemoWorkforce(): void
    {
        $debug = config('app.debug');

        /*
         * ─────────────────────────────────────────────────────────────────────
         * FLIPPED FOR THE SEEDERS, THEN PUT BACK. THE PUTTING BACK MATTERS.
         *
         * Both seeders refuse to run outside local + debug, so the environment
         * has to move for a moment. Leaving it there breaks POST tests in a way
         * that takes an hour to find: Laravel skips CSRF verification only when
         * the environment is `testing`, so a test that seeds and then posts
         * gets a 419 and no validation errors — which reads like the form
         * silently doing nothing.
         * ─────────────────────────────────────────────────────────────────────
         */
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);

        try {
            // Clients before accounts: the client accounts point at those rows.
            (new ClientSeeder)->run();
            (new AccountSeeder)->run();
            (new EmployeeSeeder)->run();
            // Teams need the employment records; projects need the teams, the
            // clients and the people.
            (new TeamSeeder)->run();
            (new ProjectSeeder)->run();
            (new TaskSeeder)->run();
            (new LeaveSeeder)->run();
            (new AttendanceSeeder)->run();
            (new SalarySeeder)->run();
            (new TicketSeeder)->run();
            (new InvoiceSeeder)->run();
            (new MeetingSeeder)->run();
            (new AnnouncementSeeder)->run();
            (new ProfileSeeder)->run();
            // Last: it replays the events the seeders above produced records
            // for, so it has to see all of them.
            app(NotificationSeeder::class)->run();
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
            config(['app.debug' => $debug]);
        }
    }

    /**
     * Empty the employees table, and everything that points at it.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * ONE PLACE, BECAUSE EVERY NEW MODULE ADDS A ROW TO IT
     *
     * Almost everything an employee touches is restricted on delete — team
     * history, written updates, attendance, leave, pay — because all of it is
     * part of their record and none of it should vanish with them. That makes
     * "there are no employees" a state a test has to build deliberately, and
     * doing it inline meant the same test breaking every time a module landed.
     *
     * Innermost first. Dropping projects cascades their team assignments and
     * updates; dropping teams cascades their memberships.
     * ─────────────────────────────────────────────────────────────────────────
     */
    protected function emptyTheWorkforce(): void
    {
        Announcement::query()->delete();
        SalaryRecord::query()->delete();
        EmployeeBanking::query()->delete();
        AttendanceRecord::query()->delete();
        LeaveRequest::query()->delete();
        Task::query()->delete();
        Project::query()->delete();
        Team::query()->delete();
        Employee::query()->delete();
    }

    /**
     * A page's own content, with the app shell stripped off.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * WHY THIS EXISTS
     *
     * The shell is on every page: sidebar, topbar, and — since Announcements
     * landed (2026-08-28) — the notification bell, which links to whatever the
     * viewer has been notified about. A notification reading "Website Redesign
     * is due in 5 days" puts `/projects/WD-2024-001` into the HTML of every
     * page in the application.
     *
     * So `assertDontSee('/projects/WD-2024-001')` on a filtered project list
     * stopped meaning "this row is filtered out" and started meaning "this id
     * appears nowhere in the document", which is a different and much weaker
     * claim. Assertions about what a PAGE shows should be made against the
     * page.
     * ─────────────────────────────────────────────────────────────────────────
     */
    protected function pageBody(string $url): string
    {
        $html = $this->get($url)->getContent();

        return $this->bodyOf($html, $url);
    }

    /**
     * The same, for HTML already fetched.
     */
    protected function bodyOf(string $html, string $context = ''): string
    {
        $start = strpos($html, '<main class="page"');
        $end = strrpos($html, '</main>');

        $this->assertNotFalse($start, 'no page body found'.($context ? " in {$context}" : ''));
        $this->assertNotFalse($end, 'no page body end found'.($context ? " in {$context}" : ''));

        return substr($html, $start, $end - $start);
    }

    /**
     * A fixed, test-only RSA key — never used outside this suite, not tied
     * to any real Google account.
     *
     * Not generated with `openssl_pkey_new()` at test time: it needs an
     * `openssl.cnf` to do that, and not every machine running this suite has
     * one on its `OPENSSL_CONF` path (Windows PHP builds commonly do not).
     * `openssl_sign()`, which App\Support\Google\GoogleAuth actually calls in
     * production, needs no such file — only generating a fresh key does — so
     * a fixed fixture key exercises the real code path without that
     * dependency.
     */
    protected const TEST_PRIVATE_KEY = <<<'PEM'
    -----BEGIN PRIVATE KEY-----
    MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCbyZPnpg6iXOsj
    yFJIPwkxuMeT6rHg5zVZmbzfRdmrQp3bw9igvjG5K5VaJSgS0ogxrmeX9pz2Z49k
    BExIOMpVvV2tA5caSHop4PukmvHDlgHD28ZxzpgTl/7OJL9Tn0c9CvXiv3SEGR+v
    H6j11tJAA9NSrZ2j3oQGR/VMMXHNFUPKWeC7VCrHd5r+TFhPkNbpHaR+1m1HM9+r
    LzCsTT/JfbGAwiBdXkQgf/dWC0r2hJxyddaRPbD2Kcj5EhBaA9WigrCUhD0v9Y5D
    oTkdQb/hww5tf48wmfOGTDjN43tt/bxI5RRBtSOCrCaFFMiWR3rZXj7Cr7Q91bCD
    fivaehGDAgMBAAECggEACB8G67lunIkXEBNcbBGESaENO76Q0RZnKul7540dFDqx
    sOSDb9MW2rb/0NgalV0OEPctN1SWEGCL570W9AsavZkNlRi1Cg24vtVCV0LXNrqI
    Ez8VwpZiEO4WofeQcchsTrur/APamjhPvE5Di7RohezmBDKykRYgDLH/LWWoPfZj
    Lu1DhgsL3dxPeugBQd+YrD0zMpouOu4pZS5Gvu5P/6usDf9Wy9AGxo8wdGpi8SMa
    2XRLyfObIRcvtEPY+JXSOAGjMb6MXjeb3v86mvURh55lkIH4SpeIM7uwgIRzcES/
    ++S3q3jFx6//n37zkv0Ndc15mA1m/RZhVhXFULTYAQKBgQDVJgLW/n9oKi7xeCI8
    EEBPP6TJj9RLEeXe3m+3JdqnCR0lJb39cnN1Y0BV1wsA4+rfxBxya2BYp+VxEiOj
    CYw5WFWkuIdaFOucEQVrwUgvmUOlSa+EA/K9ILpuFWDVg7sbazM5ybErt6XICweZ
    /wr/1Ud2UbLjh/0ikl5nwH4SgwKBgQC7G2Xfwt29++JjYU2qaQM1naR1LK6dLfLc
    nYyG0pDIZITV9pYk0Bi8/qzFoEqA0zz0uKuoBHlJ1VY773ULgR0MpngjSMqTFnpj
    EOglUK0hTKqEGPUd2T4QwLpWWDoEghuUEBAZqedM2QMXJXgFYgEiYfMxqgtOrOsF
    w7L6EQfVAQKBgQCzJ7DpXp5eQl6UrcIwtAQp2De9B3yL4K5S5qoFyfZ/wYRSzedk
    WUe8mkDgJdDk3a10iZTTg3dG7VBH+tQjXIoVRS8vNb7ms4DZ++CPkrUG9Q7LpiS1
    lM/5scGhd6ydqoyhXjh/UQzuzvy0KLkp8hofsPfQ9pii8JGO9nINSNlu2wKBgQCO
    AQY5ZAC87s1r1W6Hdfm8mG83ivjfS/81VtFPhcHihP+YD/T17YXI8pSXzMaerTNn
    HD0TYInY8nPnOx6e45fzgOhPBzDPn1C1nSBDKc6sJi6H4RUvWTBUsKD7ZSxrPX/G
    yMYfZCaq2U0SJRrJIw9vU92qBL9eL7iTgGp2hbRnAQKBgGYaG+rNcAhC1kjtCAOj
    qL/tzh5K6wI82dAD9n4YcsfAG20/MmHWwC0sBbmyvYGgag/9V+rPOhHdgzdEMkVg
    1NjaRFzHnCcRGkkTRccR+Q3tb0Ol89QsKCxkzUN+JFbrELf4GiF0GeZry6S1Bodg
    63/0fPtx6mftzXs/cNqfUGYR
    -----END PRIVATE KEY-----
    PEM;

    /**
     * A second, distinct test-only key — same reasoning as the first — for
     * tests that prove a rotation actually changed the fingerprint rather
     * than both "keys" hashing to the same value.
     */
    protected const TEST_PRIVATE_KEY_ROTATED = <<<'PEM'
    -----BEGIN PRIVATE KEY-----
    MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDDoDmJXM0gyInH
    tdAC+lZ0+Mgo4KE1wx4kmjD/xyCrvMNvno+9NseFIpvrWe6Ay2e0g70GBfAK6cHC
    zHd25GI9k1vG7moqlUK5pHZizQiSZZcUOzbAWbuUJlFt8zDQdzV8af0bHsdbIACF
    Y7cMF09puP7qCdOzB+vp/44f+X6gzp+TwwQ5gK7RzXwYGc6XRaZYCqW3y/eibREB
    lYD/V6u6Yfl2geJ9Ucmd2+No2Y41YoAqMJ4dLLEol4ADsZ5qaRw+N16YJaQS4Zk3
    8SoSqCTjoMT1gHaFW4lJVl1QgHh7SOQJG9UOc5YTkPguzUFH+03EFT3Ea++1P/a2
    LBCy4BMTAgMBAAECggEACGj/gD3KndGxRrI0zqP3ipDVfhhmJdsNFTLBz4U+jNOR
    LV/KOy67NiiOPnh/ze6wtyyAuF0R/E4fY+IJcsWPyajnpa5DRzBijXuaEVZSO4T9
    kUOYb32MGGrrTkhJvXSaio498OgKVwsRqqpmSLFJ2zg2UVZ50PUSud0gPZSq0nvs
    VF9RuwN+0m5a+pJPz1UVv8l7HNzf6XTzxNnkmsl1o2GXsmSs6hiumIXIPy96BuCw
    IoGjg4L83zSZIcMRzLhB839TAiyF1XiFU6qOxes/qJ/ibU7G9MnqGkjLyBvBs26J
    3APG6aJaD8MzgRTngn5fMLHNtoi9G7rB99XtWs/hPQKBgQDn1HLdNyEfOPhaHKT2
    iA2Tv8ZnxgakXl2LMx8vyAG2n3LhJosNESkUwUwMkdwaL5sbgFrmIojnS6xa8ifY
    I2/RPwyTCCW51UR31QL8Tbc3hKwoMEubc5PFZ0k++HfdFoyfcCqMNtgwtAGFUzy4
    ADzc4Nv8Bf/7N+NBX/ZYZLHP/wKBgQDYBX1NARSpRZaJtNTNu9GHaGhSoSIRrgQ8
    RcsA4QMlDHdVXz2KcA6oTeBRO2qOv7SsvmPSra4/go9viqMeeghE+g3PYPn2gein
    bzUCnNL7SLx0ru1TC4XPZ31HsbWrJAVWw8VcKH6TSwCAJJ/5aV+FMF1tggyVc8nc
    CPE9Nn187QKBgQDI6cCmYOnOolPx3JNGqQCDRJeRRfhpqPKO+b4UbdS2TZeE8x9d
    MrsUprTey+Yht5JIEkQ04EcflOCJbQYE9ikpAehG4K+5Ts/ovm249S1M6yk8JybP
    USoG0Y2UCkfvDmTOpKnrHHjeNJKA1nNvz5zvm9xqnVSMhpHoDV90mcfURwKBgGTn
    44jVyWXseI4opwLXCd/beGeolvZ1N8tDurjVYpeqxA9f5qpE/8PEZNTtETBPAkFy
    ycQ+ltdZ0FCFDP8Od9BokYzeOsTYF+omOzfWM4NnjYhfscIJ7t5b9BxKOZcQw2Gt
    HwKWc9GvxjMVaJijjRf06J9fkSs6o/8hOjaivmldAoGBAI+DrIuUWN7B4PYL4hd+
    pWQdbPKt9TT5miInBHF6epVqVVn26jaNj21PO0Pc5ZP6brm0pTmCnbsB7enXugNh
    279+2Z73jwaV7FmwmxncgohiqSTEGD+hA19fvVuKaEcmOyIKLUdlPEbUwFnCkX0z
    nv8jSUzFl0DbZdZ7kdkjgqys
    -----END PRIVATE KEY-----
    PEM;

    protected function fakeServiceAccountKey(
        string $email = 'zephryx-crm@test-project.iam.gserviceaccount.com',
        string $privateKey = self::TEST_PRIVATE_KEY,
    ): string {
        return json_encode([
            'type' => 'service_account',
            'client_email' => $email,
            'private_key' => $privateKey,
            'private_key_id' => 'key-'.substr(md5($email), 0, 12),
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);
    }

    /**
     * Connect Google directly, bypassing the Admin Panel's HTTP form — for
     * tests where being connected is a precondition, not the thing under
     * test. See GoogleIntegrationTest for the connect FLOW itself.
     */
    protected function connectGoogleDrive(string $sharedDriveId = 'drive-test-shared'): GoogleConnection
    {
        $key = GoogleServiceAccountKey::parse($this->fakeServiceAccountKey());

        $connection = GoogleConnection::current();

        $connection->fill([
            'service_account_email' => $key->clientEmail,
            'service_account_key' => $key->raw,
            'key_fingerprint' => $key->fingerprint(),
            'shared_drive_id' => $sharedDriveId,
            'connected_at' => now(),
        ])->save();

        return $connection;
    }

    /**
     * Fake the whole HTTP round trip a `DocumentStore::put()` makes once
     * Google is connected: the token exchange, the module-folder lookup
     * (assumed not to exist yet, so it is created), the file metadata create,
     * and the content write. Doesn't fake the token endpoint's URL under a
     * query string, so this composes with a caller's own `Http::fake()` for
     * anything more specific — see DocumentStoreTest for that shape instead.
     */
    protected function fakeDriveUpload(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fake-token'], 200),
            'https://www.googleapis.com/drive/v3/files?*' => Http::sequence()
                ->push(['files' => []], 200) // folder lookup: none found
                ->push(['id' => 'folder-id'], 200) // folder created
                ->push(['id' => 'file-id'], 200), // file metadata created
            'https://www.googleapis.com/upload/drive/v3/files/*' => Http::response(['id' => 'file-id'], 200),
        ]);
    }
}
