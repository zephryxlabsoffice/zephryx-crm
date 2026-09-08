<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use App\Support\Realm;
use Database\Seeders\AccountSeeder;
use Database\Seeders\EmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
     * Sign in as a client. `client_ref` is what every ownership check in the
     * portal resolves through (§6).
     */
    protected function signInAsClient(string $client = 'DGL International School'): User
    {
        $user = User::factory()->create([
            'user_id' => 'CLI-T'.fake()->unique()->numberBetween(100, 999),
            'account_type' => Realm::CLIENT,
            'staff_kind' => null,
            'status' => 'active',
            'client_ref' => $client,
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
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);

        (new AccountSeeder)->run();
        (new EmployeeSeeder)->run();
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

        $this->assertNotFalse($start, "no page body found".($context ? " in {$context}" : ''));
        $this->assertNotFalse($end, "no page body end found".($context ? " in {$context}" : ''));

        return substr($html, $start, $end - $start);
    }
}
