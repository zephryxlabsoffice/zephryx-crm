<?php

namespace Tests\Feature;

use App\Support\Admin\Retroactive;
use App\Support\Admin\SettingsCatalogue;
use App\Support\AttendancePolicy;
use App\Models\MasterDataItem;
use App\Support\Admin\AccessDirectory;
use App\Support\Admin\AuditDirectory;
use App\Support\Admin\CompanySettings;
use App\Support\Admin\MasterDataDirectory;
use App\Support\Rbac\Rbac;
use Tests\TestCase;

/**
 * The Admin Panel.
 *
 * Two properties carry most of the weight here, and neither is about layout:
 *
 *   1. §2.1 — this realm has NO OPERATIONAL AUTHORITY. It configures who may
 *      approve leave and can never approve any. Enforced by absence, so the
 *      test is about routes that must not exist.
 *
 *   2. Several settings re-judge records that already exist, because
 *      attendance and leave are derived on read. The panel has to name that
 *      before it saves.
 */
class AdminPanelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * The panel is behind `realm:admin` (§3.1), and the owner account holds
         * no roles at all — its capabilities are implicit in the account type,
         * because §2.1 makes the Admin Panel "not a role and not assignable"
         * (Rbac::ADMIN_BASE).
         */
        $this->signInAsAdmin();
    }

    protected function withDemoData(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);
    }

    /**
     * A POST carrying a real CSRF token.
     *
     * Needed because withDemoData() moves the environment off `testing`, which
     * is what Laravel's CSRF middleware checks to decide whether to skip. The
     * token is supplied rather than the middleware disabled: these routes are
     * meant to require one, and a test that turned the check off would stop
     * proving that they do.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function postWithToken(string $url, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->withSession(['_token' => 'test-token'])
            ->post($url, $payload + ['_token' => 'test-token']);
    }

    /* ══════════════════════════════════════════════════════════════════════
       §2.1 — SETS THE RULES, DOES NOT PARTICIPATE IN THEM
       ══════════════════════════════════════════════════════════════════════ */

    public function test_no_admin_route_performs_an_operational_act(): void
    {
        /*
         * The panel cannot approve leave, run payroll, mark attendance, raise
         * an invoice or assign a task. Enforced by absence: a permission
         * somebody forgot to write is a hole, but a route that does not exist
         * cannot be called.
         *
         * Driven off the router rather than a list here, so adding one fails.
         */
        $operational = [
            'approve', 'reject', 'pay', 'payslip', 'check-in', 'check-out',
            'invoice', 'task', 'salary', 'leave', 'attendance',
        ];

        foreach (app('router')->getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! str_starts_with($name, 'admin.')) {
                continue;
            }

            foreach ($operational as $word) {
                $this->assertStringNotContainsString(
                    $word,
                    $name,
                    'the admin route '.$name.' looks operational (§2.1)',
                );
            }
        }
    }

    public function test_the_admin_sidebar_carries_no_operational_module(): void
    {
        // No Leave, Salary, Attendance, Projects or Tasks. This account has no
        // personal records and no authority over anybody else's.
        $routes = array_column((array) config('navigation-admin'), 'route');

        foreach ($routes as $route) {
            $this->assertStringStartsWith('admin.', $route, $route.' is not an admin route');
        }

        $labels = array_column((array) config('navigation-admin'), 'label');

        foreach (['Leave Requests', 'Salary', 'Attendance', 'Projects', 'Tasks', 'My Profile'] as $operational) {
            $this->assertNotContains($operational, $labels);
        }
    }

    public function test_the_audit_log_cannot_be_written_or_cleared(): void
    {
        /*
         * An audit log with a delete button is not an audit log, and this is
         * the account whose actions most need the record. GET only, in both
         * directions.
         */
        foreach (app('router')->getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! str_starts_with($name, 'admin.audit')) {
                continue;
            }

            $this->assertSame(
                ['GET', 'HEAD'],
                $route->methods(),
                $name.' is not read-only',
            );
        }
    }

    public function test_no_route_deletes_an_account_or_a_master_data_row(): void
    {
        // Suspending, not deleting: a deleted user is a payroll record with
        // nobody attached. A deleted department orphans every employee in it.
        $this->assertFalse(app('router')->has('admin.accounts.destroy'));
        $this->assertFalse(app('router')->has('admin.master.destroy'));
        $this->assertTrue(app('router')->has('admin.accounts.status'));
        $this->assertTrue(app('router')->has('admin.master.deactivate'));
    }

    /* ══════════════════════════════════════════════════════════════════════
       SETTINGS THAT REWRITE THE PAST
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_settings_that_rewrite_history_are_marked_as_such(): void
    {
        $retroactive = SettingsCatalogue::retroactiveKeys();

        /*
         * These four are derived on read by AttendancePolicy and LeavePolicy,
         * so changing one re-judges records that already exist. If a future
         * edit drops the flag, the warning and the preview both disappear
         * silently — which is exactly the failure this catalogue exists to
         * prevent.
         */
        foreach ([
            'attendance.half_day_hours',
            'attendance.auto_reject_after_hours',
            'attendance.week_off',
        ] as $key) {
            $this->assertContains($key, $retroactive, $key.' is no longer marked retroactive');
        }

        // And the harmless ones are not, or every change would demand a
        // confirmation nobody reads.
        $this->assertNotContains('zephryx.brand.name', $retroactive);
        $this->assertNotContains('zephryx.support.email', $retroactive);
    }

    public function test_changing_the_half_day_threshold_reports_what_it_would_reclassify(): void
    {
        $this->seedDemoWorkforce();
        $this->withDemoData();

        // Well above the current threshold, so days that count as full become
        // half days.
        $effect = Retroactive::preview('attendance.half_day_hours', 9.0);

        $this->assertGreaterThan(0, $effect['affected'], 'a large threshold change reclassified nothing');
        $this->assertGreaterThan(0, $effect['people']);
        $this->assertNotEmpty($effect['changes']);
        $this->assertStringContainsString('re-judged', $effect['summary']);
    }

    public function test_the_preview_does_not_leak_its_proposed_value_into_the_running_config(): void
    {
        $this->withDemoData();

        $before = AttendancePolicy::halfDayHours();

        Retroactive::preview('attendance.half_day_hours', 9.0);

        /*
         * The preview evaluates the history twice, once with the proposed value
         * pushed into config. If it failed to restore, the very page about to
         * render the warning would already be using the new rule — and every
         * other request in the process with it.
         */
        $this->assertSame($before, AttendancePolicy::halfDayHours());
    }

    public function test_a_setting_that_does_not_move_reports_nothing(): void
    {
        $this->withDemoData();

        $effect = Retroactive::preview('attendance.half_day_hours', AttendancePolicy::halfDayHours());

        $this->assertSame(0, $effect['affected']);
    }

    public function test_the_confirmation_names_the_effect_before_anything_is_saved(): void
    {
        $this->withDemoData();

        $response = $this->postWithToken('/admin/settings/preview', [
            'key' => 'attendance.half_day_hours',
            'value' => 9.0,
        ]);

        $response->assertOk();

        $body = $this->bodyOf($response->getContent(), '/admin/settings/preview');

        // The effect, not just the value — the whole point of the two-step.
        $this->assertStringContainsString('What this does to existing records', $body);
        $this->assertStringContainsString('re-judged', $body);

        // And nothing is written from this step.
        $this->assertSame(9.0, 9.0);
        $this->assertSame(4.0, AttendancePolicy::halfDayHours());
    }

    public function test_a_setting_whose_value_is_a_set_previews_correctly(): void
    {
        $this->seedDemoWorkforce();
        $this->withDemoData();

        /*
         * The weekly off is a list, not a scalar, and it arrives from the form
         * as an array of strings. Two things went wrong here and both were
         * invisible to a scalar-only test: the confirmation crashed comparing
         * an array to a string, and the policy's strict in_array would have
         * matched no day at all, reporting "nothing changes" for the single
         * most dramatic setting on the page.
         */
        $response = $this->postWithToken('/admin/settings/preview', [
            'key' => 'attendance.week_off',
            'value' => ['0', '6'],
        ]);

        $response->assertOk();

        $body = $this->bodyOf($response->getContent(), '/admin/settings/preview');

        $this->assertStringContainsString('What this does to existing records', $body);

        // Written out, not printed raw. "0, 6" is correct and unreadable on the
        // screen where somebody confirms what they are agreeing to.
        $this->assertStringContainsString('Saturday', $body);
        $this->assertStringNotContainsString('<strong>0, 6</strong>', $body);

        // Adding Saturday turns every past Saturday into a week-off, so this
        // must report a real number rather than zero.
        $effect = Retroactive::preview('attendance.week_off', [0, 6]);
        $this->assertGreaterThan(0, $effect['affected'], 'adding a weekly off reclassified nothing');

        /*
         * The examples have to look like the summary. Taken in natural order
         * they came out as eight consecutive Saturdays belonging to one person,
         * under a heading claiming eleven people were affected — which reads as
         * though the number is wrong.
         */
        $this->assertGreaterThan(
            1,
            collect($effect['changes'])->pluck('who')->unique()->count(),
            'the examples are all one person',
        );
    }

    public function test_clearing_a_set_is_reviewable_rather_than_refused(): void
    {
        $this->withDemoData();

        // Removing every weekly off turns each past Sunday into a working day
        // people did not attend. It is a legitimate change and the one most
        // worth reviewing, so the form must not reject it as empty.
        $this->postWithToken('/admin/settings/preview', [
            'key' => 'attendance.week_off',
            'value' => [],
        ])->assertOk();
    }

    public function test_the_confirmation_is_a_post_so_proposed_values_stay_out_of_urls(): void
    {
        // Same reason salary.pay.confirm is a POST: values a person is about to
        // confirm should not end up bookmarked or in an access log.
        $route = app('router')->getRoutes()->getByName('admin.settings.preview');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertNotContains('GET', $route->methods());
    }

    public function test_an_unknown_setting_key_is_refused(): void
    {
        $this->withDemoData();

        // The catalogue is the definition of what a setting is. A posted key
        // outside it is not one, whatever the form said.
        $this->postWithToken('/admin/settings/preview', [
            'key' => 'app.key',
            'value' => 'nonsense',
        ])->assertNotFound();
    }

    /* ══════════════════════════════════════════════════════════════════════
       SAVING A SETTING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_saved_setting_takes_effect_and_survives_a_deploy(): void
    {
        /*
         * Written to `company_settings`, not to a config file. A deployed
         * application cannot edit its own source, and a value in a file is one
         * the next deploy silently reverts — so the assertion is on the row as
         * well as on the running value.
         */
        $this->seedDemoWorkforce();
        $this->withDemoData();

        $this->assertSame(4.0, AttendancePolicy::halfDayHours());

        $this->postWithToken('/admin/settings', [
            'key' => 'attendance.half_day_hours',
            'value' => '6',
        ])->assertRedirect('/admin/settings');

        $this->assertDatabaseHas('company_settings', ['key' => 'attendance.half_day_hours']);
        $this->assertSame(6.0, AttendancePolicy::halfDayHours());
    }

    public function test_the_stored_value_wins_over_the_shipped_default_on_the_next_request(): void
    {
        /*
         * The overlay, and the reason it is applied at boot rather than at each
         * call site: twenty-odd places read these values, and a version where
         * every one had to ask a settings service is a version where one forgot
         * and quietly kept applying last year's rule.
         */
        $this->withDemoData();

        $this->postWithToken('/admin/settings', [
            'key' => 'attendance.half_day_hours',
            'value' => '6',
        ])->assertRedirect();

        // A fresh request, with config rebuilt from the file defaults first.
        config(['attendance.half_day_hours' => 4.0]);
        CompanySettings::apply();

        $this->assertSame(6.0, AttendancePolicy::halfDayHours());
    }

    public function test_a_key_outside_the_catalogue_cannot_be_written(): void
    {
        /*
         * `config([$key => …])` with an unchecked key would let a posted field
         * rewrite anything in the configuration. The catalogue is the
         * definition of what a setting is, and the form is not the guard.
         */
        $this->withDemoData();

        $this->postWithToken('/admin/settings', ['key' => 'app.key', 'value' => 'nonsense'])
            ->assertNotFound();

        $this->assertDatabaseCount('company_settings', 0);
    }

    public function test_a_save_against_a_stale_preview_is_refused(): void
    {
        /*
         * The figures somebody read were computed on the previous request. If
         * they have moved, the second step is agreement to a number nobody ever
         * saw — so it sends them back to look at the new one rather than
         * writing against the old.
         */
        $this->withDemoData();

        $this->postWithToken('/admin/settings', [
            'key' => 'attendance.half_day_hours',
            'value' => '6',
            'fingerprint' => 'a figure from some other afternoon',
        ])->assertRedirect('/admin/settings');

        $this->assertDatabaseCount('company_settings', 0);
        $this->assertSame(4.0, AttendancePolicy::halfDayHours());
    }

    public function test_the_audit_entry_carries_the_effect_and_not_only_the_value(): void
    {
        $this->seedDemoWorkforce();
        $this->withDemoData();

        $this->postWithToken('/admin/settings', [
            'key' => 'attendance.half_day_hours',
            'value' => '9',
        ])->assertRedirect();

        $entry = \Illuminate\Support\Facades\DB::table('audit_log')
            ->where('action', \App\Support\Audit\AuditLog::SETTING_CHANGED)
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);

        // "half_day_hours: 4 → 9" is true and useless. What happened is that
        // days already worked now read differently.
        $this->assertStringContainsString('4', $entry->before_json);
        $this->assertStringContainsString('re-judged', $entry->after_json);
    }

    public function test_a_set_valued_setting_round_trips_through_the_table(): void
    {
        /*
         * The weekly off is a list of integers and the policy compares with a
         * strict in_array. Flattened to a string on the way in and parsed back
         * as strings on the way out, it would match no day at all — and the
         * symptom is a weekly off that silently stops applying.
         */
        $this->withDemoData();

        $this->postWithToken('/admin/settings', [
            'key' => 'attendance.week_off',
            'value' => ['0', '6'],
        ])->assertRedirect();

        config(['attendance.week_off' => [0]]);
        CompanySettings::apply();

        $this->assertSame([0, 6], config('attendance.week_off'));
    }

    /* ══════════════════════════════════════════════════════════════════════
       ACCESS CONTROL
       ══════════════════════════════════════════════════════════════════════ */

    public function test_every_permission_the_application_uses_can_be_granted(): void
    {
        $keys = collect(AccessDirectory::permissions())->flatten(1)->pluck('key');

        /*
         * The demo source DERIVED this list by reading keys back out of the
         * navigation config and the dashboard registry; the catalogue is the
         * `permissions` table now, seeded by RbacSeeder.
         *
         * The property is unchanged and still worth asserting: a key the
         * application gates on but the Admin Panel cannot grant is a module
         * that is invisible to everyone with no explanation.
         *
         * Checked against the two staff-facing sidebars and the dashboard
         * registry — not navigation-admin, which is the realm base and is
         * deliberately ungrantable (see the test below).
         */
        foreach (['navigation', 'navigation-client'] as $file) {
            foreach ((array) config($file) as $entry) {
                if (str_starts_with($entry['permission'], 'client.')) {
                    // A realm base too (§2.2): held by account type, never by a
                    // role. Its absence here is the point of the next test.
                    continue;
                }

                $this->assertTrue(
                    $keys->contains($entry['permission']),
                    $entry['permission'].' is used by '.$file.' but cannot be granted',
                );
            }
        }

        foreach ((array) config('dashboard.widgets') as $widget) {
            $this->assertTrue($keys->contains($widget['permission']), $widget['permission'].' cannot be granted');
        }
    }

    public function test_the_write_permissions_the_backend_added_are_grantable(): void
    {
        /*
         * The gap the table closed. Derivation could only see keys some sidebar
         * or widget mentioned, so every permission gating a write route and
         * nothing else — the whole of RbacSeeder::MODULE_WRITES — was invisible
         * to the screen that assigns it.
         */
        $keys = collect(AccessDirectory::permissions())->flatten(1)->pluck('key');

        foreach (\Database\Seeders\RbacSeeder::MODULE_WRITES as $key) {
            $this->assertTrue($keys->contains($key), $key.' gates a route but cannot be granted');
        }
    }

    public function test_admin_permissions_are_not_offered_to_any_role(): void
    {
        /*
         * §2.1: the Admin Panel is "not a role and not assignable". It is an
         * account type in its own realm.
         *
         * Offering `admin.settings.view` as a toggle on the HR page would grant
         * nothing — realm middleware refuses /admin to a staff session whatever
         * keys it holds — and would imply the panel's authority is something a
         * person can be given a piece of.
         */
        $keys = collect(AccessDirectory::permissions())->flatten(1)->pluck('key');

        foreach ((array) config('navigation-admin') as $entry) {
            $this->assertFalse(
                $keys->contains($entry['permission']),
                $entry['permission'].' is offered as a role permission',
            );
        }

        // And the write refuses one too, not merely the form: a request naming
        // an admin key is rejected rather than silently dropped.
        $this->postWithToken('/admin/access/hr', ['permissions' => ['admin.settings.view']])
            ->assertSessionHasErrors('permissions.0');

        $body = $this->pageBody('/admin/access/hr');

        $this->assertStringNotContainsString('admin.settings.view', $body);
        // And the page says why it is absent rather than leaving a gap.
        $this->assertStringContainsString('The Admin Panel', $body);
    }

    public function test_a_permission_toggle_names_who_it_would_land_on(): void
    {
        $this->seedDemoWorkforce();

        $body = $this->pageBody('/admin/access/manager');

        /*
         * The click is made looking at a role name; the consequence lands on
         * people whose names are not otherwise on the page. Without this
         * sentence the screen is a matrix, and a matrix is how an organisation
         * ends up not knowing who can see payroll.
         */
        $this->assertStringContainsString('Granting gives it to', $body);

        $holders = AccessDirectory::holdersOf('manager');

        $this->assertNotEmpty($holders, 'nobody holds the role, so this proves nothing');

        foreach ($holders as $person) {
            $this->assertStringContainsString($person['name'], $body);
        }
    }

    public function test_the_blast_radius_excludes_people_who_already_hold_it_elsewhere(): void
    {
        $this->seedDemoWorkforce();

        /*
         * Roles stack as a union (§2.4). Granting salary.view to Employee — a
         * role everybody holds — must not count the HR staff who already have
         * it, or the number overstates the change.
         */
        $everyone = AccessDirectory::whoWouldHold('salary.view', 'employee');
        $hrHolders = AccessDirectory::holdersOf('hr');

        $this->assertNotEmpty($hrHolders, 'nobody holds HR, so this proves nothing');

        foreach ($hrHolders as $person) {
            $this->assertFalse(
                $everyone->contains('user_id', $person['user_id']),
                $person['name'].' already holds salary.view through HR and should not be counted',
            );
        }
    }

    public function test_the_employee_base_is_not_editable_as_a_role(): void
    {
        /*
         * §5: it is not a role, and it is granted implicitly precisely so a
         * role edit cannot revoke it. An owner who could remove it would take
         * everyone's own attendance, leave and payslips away at once.
         */
        $this->assertNull(AccessDirectory::role('employee_base'));
        $this->get('/admin/access/employee_base')->assertNotFound();

        // And the page says why it is absent, rather than leaving a puzzle.
        $this->assertStringContainsString('The Employee base', $this->pageBody('/admin/access/hr'));
    }

    public function test_rank_is_per_domain_and_hr_outranks_system_in_people(): void
    {
        // The row that makes rank per-domain rather than one ladder (§2.5).
        $hr = AccessDirectory::role('hr');
        $ceo = AccessDirectory::role('ceo');

        $this->assertGreaterThan($hr['ranks']['system'], $hr['ranks']['people']);
        $this->assertGreaterThan(0, $ceo['ranks']['finance']);

        // A Mentor has no standing anywhere — read-only, no Employee base.
        $this->assertSame(0, array_sum(AccessDirectory::role('mentor')['ranks']));
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE ACCESS AND ACCOUNT WRITES
       ══════════════════════════════════════════════════════════════════════ */

    public function test_granting_a_permission_takes_effect_immediately(): void
    {
        $this->seedDemoWorkforce();

        $holder = AccessDirectory::holdersOf('manager')->first();

        $this->assertNotNull($holder, 'nobody holds the role, so this proves nothing');

        $user = \App\Models\User::where('user_id', $holder['user_id'])->firstOrFail();

        $this->assertFalse(app(Rbac::class)->can($user, 'salary.view'));

        $manager = \App\Models\Role::where('role_key', 'manager')->firstOrFail();

        $this->postWithToken('/admin/access/manager', [
            'permissions' => [...$manager->permissions->pluck('permission_key')->all(), 'salary.view'],
        ])->assertRedirect();

        app(Rbac::class)->forget();

        $this->assertTrue(app(Rbac::class)->can($user->fresh(), 'salary.view'));
    }

    public function test_the_audit_entry_for_a_permission_change_names_the_people(): void
    {
        /*
         * The entry that answers the question somebody brings six months later.
         * "Now holds 14 permissions" does not; "granted salary.view, which gave
         * it to Rahul Mehta and Vikram Joshi" does.
         */
        $this->seedDemoWorkforce();

        $names = AccessDirectory::whoWouldHold('salary.view', 'manager')->pluck('name');

        $this->assertNotEmpty($names, 'nobody would be affected, so this proves nothing');

        $manager = \App\Models\Role::where('role_key', 'manager')->firstOrFail();

        $this->postWithToken('/admin/access/manager', [
            'permissions' => [...$manager->permissions->pluck('permission_key')->all(), 'salary.view'],
        ])->assertRedirect();

        $entry = \Illuminate\Support\Facades\DB::table('audit_log')
            ->where('action', \App\Support\Audit\AuditLog::PERMISSION_CHANGED)
            ->latest('id')
            ->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString('granted salary.view', $entry->after_json);

        foreach ($names as $name) {
            $this->assertStringContainsString($name, $entry->after_json);
        }
    }

    public function test_suspending_an_account_signs_it_out_of_everywhere(): void
    {
        /*
         * §4.4 and §4.6. An account that cannot sign in but is already signed
         * in is not suspended, and a remembered cookie would sign it straight
         * back in.
         */
        $this->seedDemoWorkforce();

        $user = \App\Models\User::where('user_id', 'EMP002')->firstOrFail();

        \Illuminate\Support\Facades\DB::table('trusted_devices')->insert([
            'user_id' => $user->id,
            'token_hash' => 'whatever',
            'trusted_until' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postWithToken('/admin/accounts/EMP002/status', [
            'status' => 'suspended',
            'reason' => 'Left the company.',
        ])->assertRedirect();

        $this->assertSame('suspended', $user->fresh()->status);

        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('trusted_devices')
            ->where('user_id', $user->id)->whereNull('revoked_at')->count());

        // And an inactive account holds nothing, whatever roles it still has.
        app(Rbac::class)->forget();
        $this->assertSame([], app(Rbac::class)->permissionsFor($user->fresh()));
    }

    public function test_suspending_asks_for_a_reason_and_restoring_does_not(): void
    {
        $this->seedDemoWorkforce();

        $this->postWithToken('/admin/accounts/EMP002/status', ['status' => 'suspended'])
            ->assertSessionHasErrors('reason');

        $this->postWithToken('/admin/accounts/EMP002/status', [
            'status' => 'suspended', 'reason' => 'Left the company.',
        ])->assertRedirect();

        $this->postWithToken('/admin/accounts/EMP002/status', ['status' => 'active'])
            ->assertRedirect();

        $this->assertSame('active', \App\Models\User::where('user_id', 'EMP002')->firstOrFail()->status);
    }

    public function test_the_owner_account_cannot_be_reached_by_either_write(): void
    {
        /*
         * It is the only account that can open this page, so one click would
         * lock the company out of its own configuration. Excluded from the
         * directory's query rather than refused by the write — a row nobody may
         * act on is a row that invites the attempt.
         */
        $owner = \App\Models\User::where('account_type', \App\Support\Realm::ADMIN)->firstOrFail();

        $this->get('/admin/accounts/'.$owner->user_id)->assertNotFound();

        $this->postWithToken('/admin/accounts/'.$owner->user_id.'/status', [
            'status' => 'suspended', 'reason' => 'Trying it on.',
        ])->assertNotFound();

        $this->postWithToken('/admin/accounts/'.$owner->user_id.'/roles', ['roles' => []])
            ->assertNotFound();

        $this->assertSame('active', $owner->fresh()->status);
    }

    public function test_saving_roles_records_what_moved_and_who_assigned_it(): void
    {
        $this->seedDemoWorkforce();

        $user = \App\Models\User::where('user_id', 'EMP002')->firstOrFail();

        $this->postWithToken('/admin/accounts/EMP002/roles', ['roles' => ['employee', 'hr']])
            ->assertRedirect();

        $this->assertSame(['employee', 'hr'], $user->fresh()->roles->pluck('role_key')->sort()->values()->all());

        // §8's `assigned_by` is the column that only makes sense if assignment
        // happens somewhere with an accountable actor. This is that somewhere.
        $pivot = \Illuminate\Support\Facades\DB::table('user_roles')->where('user_id', $user->id)->first();
        $this->assertNotNull($pivot->assigned_by);
        $this->assertNotNull($pivot->assigned_at);

        $entry = \Illuminate\Support\Facades\DB::table('audit_log')
            ->where('action', \App\Support\Audit\AuditLog::ACCOUNT_CHANGED)
            ->latest('id')
            ->first();

        // What MOVED, not what the set now is.
        $this->assertStringContainsString('granted hr', $entry->after_json);
    }

    public function test_a_client_account_cannot_be_given_a_staff_role(): void
    {
        /*
         * §2.2: everything a client can reach comes from the realm base.
         * Granting it a staff role would put keys on an account realm
         * middleware refuses anyway — a grant that reads as real and does
         * nothing.
         */
        $this->seedDemoWorkforce();

        $client = \App\Models\User::where('account_type', \App\Support\Realm::CLIENT)->firstOrFail();

        $this->postWithToken('/admin/accounts/'.$client->user_id.'/roles', ['roles' => ['hr']])
            ->assertForbidden();

        $this->assertSame(0, $client->fresh()->roles()->count());
    }

    /* ══════════════════════════════════════════════════════════════════════
       MASTER DATA AND THE REST
       ══════════════════════════════════════════════════════════════════════ */

    public function test_master_data_states_what_uses_a_row_before_it_is_retired(): void
    {
        $this->seedDemoWorkforce();

        $body = $this->pageBody('/admin/master-data/departments');

        // The count is what makes retiring something a decision rather than a
        // click, and it is counted from the records, not stored.
        $this->assertStringContainsString('In use', $body);
        $this->assertStringContainsString('There is no delete', $body);

        $this->assertGreaterThan(
            0,
            MasterDataDirectory::rows('departments')->sum('in_use'),
            'no department has anybody in it, so this proves nothing',
        );
    }

    public function test_an_uncountable_list_says_so_rather_than_reporting_zero(): void
    {
        /*
         * My Profile files documents against a fixed set of kinds rather than
         * against this list, so nothing anywhere points at these rows. Zero
         * would read as "nothing uses this, retiring it costs nothing"; the
         * truth is that nobody is looking.
         */
        $this->assertNull(MasterDataDirectory::totalInUse('document-types'));

        $body = $this->pageBody('/admin/master-data/document-types');

        $this->assertStringContainsString('Not counted', $body);
        $this->assertStringNotContainsString('Nothing uses it', $body);
    }

    public function test_retiring_a_row_keeps_it_and_stops_offering_it(): void
    {
        $this->seedDemoWorkforce();

        $item = MasterDataItem::query()->inList('departments')->active()->firstOrFail();

        $this->postWithToken('/admin/master-data/departments/deactivate', ['item' => $item->id])
            ->assertRedirect();

        // Kept, not deleted. Everything pointing at it still reads correctly.
        $this->assertDatabaseHas('master_data_items', ['id' => $item->id, 'is_active' => false]);

        // And no longer offered where a new record would pick one.
        $this->assertFalse(
            MasterDataItem::query()->inList('departments')->active()->pluck('id')->contains($item->id),
        );
    }

    public function test_the_last_active_row_in_a_list_cannot_be_retired(): void
    {
        /*
         * An employee form with no departments to choose from creates nobody,
         * and the person retiring the last one is looking at a row rather than
         * at the list.
         */
        $this->seedDemoWorkforce();

        MasterDataItem::query()->inList('departments')->active()->get()->skip(1)
            ->each(fn (MasterDataItem $item) => $item->update(['is_active' => false]));

        $last = MasterDataItem::query()->inList('departments')->active()->firstOrFail();

        $this->postWithToken('/admin/master-data/departments/deactivate', ['item' => $last->id])
            ->assertSessionHasErrors('item');

        $this->assertTrue($last->fresh()->is_active);
    }

    public function test_adding_a_retired_code_back_brings_the_row_back_rather_than_duplicating_it(): void
    {
        /*
         * Somebody retires Operations, then a year later adds it again. A
         * second row would give the list two Operations, one of which quietly
         * holds all the history.
         */
        $item = MasterDataItem::query()->inList('departments')->firstOrFail();

        $item->update(['is_active' => false]);

        $before = MasterDataItem::query()->inList('departments')->count();

        $this->postWithToken('/admin/master-data/departments', [
            'name' => $item->name,
            'code' => $item->code,
        ])->assertRedirect();

        $this->assertSame($before, MasterDataItem::query()->inList('departments')->count());
        $this->assertTrue($item->fresh()->is_active);
    }

    public function test_a_code_is_unique_within_its_list_and_not_across_the_table(): void
    {
        /*
         * The four lists share a table because they share a shape and a screen.
         * They are still four lists — refusing a document type its code because
         * a department has one would be the table leaking onto the screen.
         */
        $department = MasterDataItem::query()->inList('departments')->firstOrFail();

        $this->postWithToken('/admin/master-data/document-types', [
            'name' => 'Something else entirely',
            'code' => $department->code,
        ])->assertRedirect();

        $this->assertDatabaseHas('master_data_items', [
            'list' => 'document-types',
            'code' => $department->code,
        ]);

        // And a genuine duplicate within one list is refused.
        $this->postWithToken('/admin/master-data/departments', [
            'name' => 'A new name',
            'code' => $department->code,
        ])->assertSessionHasErrors('code');
    }

    public function test_nothing_in_master_data_deletes(): void
    {
        // The whole rule, asserted on the router rather than on a page.
        foreach (app('router')->getRoutes() as $route) {
            if (! str_contains($route->uri(), 'master-data')) {
                continue;
            }

            $this->assertNotContains('DELETE', $route->methods(), $route->uri().' accepts DELETE');
            $this->assertStringNotContainsString('delete', $route->uri());
        }
    }

    public function test_an_unknown_master_data_list_is_not_found(): void
    {
        $this->get('/admin/master-data/salaries')->assertNotFound();
        $this->postWithToken('/admin/master-data/salaries', ['name' => 'X', 'code' => 'X'])->assertNotFound();
    }

    public function test_the_audit_entry_records_the_effect_and_not_only_the_value(): void
    {
        $this->seedDemoWorkforce();
        $this->withDemoData();

        $this->postWithToken('/admin/settings', [
            'key' => 'attendance.half_day_hours',
            'value' => '9',
        ])->assertRedirect();

        $entry = AuditDirectory::query('setting')->get()->first();

        $this->assertNotNull($entry);

        $row = AuditDirectory::row($entry);

        /*
         * "half_day_hours: 4 → 9" is true and useless. The log has to carry
         * what the change did, because that is what somebody comes here for.
         */
        $this->assertNotSame('', $row['before']);
        $this->assertStringContainsString('re-judged', $row['after']);

        // §6 requires all of these on every entry.
        foreach (['actor', 'action', 'entity', 'before', 'after', 'ip', 'agent'] as $field) {
            $this->assertArrayHasKey($field, $row);
        }
    }

    public function test_the_audit_filter_covers_every_kind_the_log_holds(): void
    {
        /*
         * The demo source named five kinds, because those are the five §6 calls
         * out and the log held nothing else. It holds every module write now —
         * and a five-entry filter over sixty action types is worse than no
         * filter: most of the page would be unreachable through it, and the tab
         * counts would not add up to the total.
         */
        $this->seedDemoWorkforce();

        $this->postWithToken('/admin/accounts/EMP002/roles', ['roles' => ['employee', 'hr']])->assertRedirect();
        $this->postWithToken('/admin/settings', [
            'key' => 'attendance.half_day_hours', 'value' => '5',
        ])->assertRedirect();

        $counts = AuditDirectory::counts();
        $kinds = AuditDirectory::kinds();

        $this->assertNotEmpty($kinds);

        $summed = collect($kinds)->keys()->sum(fn (string $kind) => $counts[$kind]);

        $this->assertSame($counts['total'], $summed, 'the tab counts do not add up to the total');
    }

    public function test_the_audit_log_has_no_write_route_in_either_direction(): void
    {
        /*
         * An audit log with a delete button is not an audit log, and this is
         * the account whose actions most need the record. Asserted on the
         * router rather than on a page: the risk is a route somebody adds, not
         * a button somebody draws.
         */
        foreach (app('router')->getRoutes() as $route) {
            if (! str_contains($route->uri(), 'audit')) {
                continue;
            }

            $this->assertSame(['GET', 'HEAD'], $route->methods(), $route->uri().' is not read-only');
        }

        /*
         * And the writing class offers one write and no way to unwrite. Named
         * individually rather than by counting methods: the class is allowed to
         * grow readers — the audit page itself uses one — and a test that broke
         * when it did would be deleted rather than fixed.
         */
        foreach (['update', 'delete', 'truncate', 'prune', 'forget', 'clear'] as $method) {
            $this->assertFalse(
                method_exists(\App\Support\Audit\AuditLog::class, $method),
                'AuditLog::'.$method.'() exists, and an audit log the application can edit is not one',
            );
        }

        $this->assertTrue(method_exists(\App\Support\Audit\AuditLog::class, 'record'));
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE SHELL
       ══════════════════════════════════════════════════════════════════════ */

    public function test_every_page_renders(): void
    {
        $this->seedDemoWorkforce();
        $this->withDemoData();

        foreach ((array) config('navigation-admin') as $entry) {
            $this->get(route($entry['route']))->assertOk();
        }

        $this->get('/admin/access/hr')->assertOk();
        $this->get('/admin/accounts/EMP005')->assertOk();
        $this->get('/admin/master-data/departments')->assertOk();

        // A real entry, written by a real change rather than seeded.
        $this->postWithToken('/admin/accounts/EMP002/roles', ['roles' => ['employee', 'hr']])
            ->assertRedirect();

        $entry = AuditDirectory::recent(1)->firstOrFail();

        $this->get('/admin/audit/'.$entry['id'])->assertOk();
        $this->get('/admin/audit/AUD-99999999')->assertNotFound();
    }

    public function test_the_admin_sidebar_shows_no_staff_or_client_entries(): void
    {
        $this->withDemoData();

        $html = $this->get('/admin/dashboard')->getContent();

        $adminKeys = array_column((array) config('navigation-admin'), 'key');

        foreach ((array) config('navigation') as $entry) {
            if (in_array($entry['key'], $adminKeys, true)) {
                continue;
            }

            $this->assertStringNotContainsString(
                '>'.$entry['label'].'</span>',
                $html,
                'the staff entry "'.$entry['label'].'" is in the admin sidebar',
            );
        }
    }

    public function test_it_renders_its_empty_states_without_demo_data(): void
    {
        // Outside local + debug every Demo source returns nothing.
        foreach ((array) config('navigation-admin') as $entry) {
            $this->get(route($entry['route']))->assertOk();
        }
    }

    public function test_it_renders_nothing_the_content_security_policy_would_block(): void
    {
        $this->withDemoData();

        foreach (['/admin/dashboard', '/admin/settings', '/admin/access/hr', '/admin/audit'] as $page) {
            $html = $this->get($page)->getContent();

            $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), $page.': inline <style>');
            $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), $page.': inline style attribute');
            $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), $page.': inline event handler');
            $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), $page.': inline <script>');
        }
    }
}
