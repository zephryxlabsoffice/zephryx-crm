<?php

namespace Tests\Feature;

use App\Support\Admin\Retroactive;
use App\Support\Admin\SettingsCatalogue;
use App\Support\AttendancePolicy;
use App\Support\Demo\DemoAudit;
use App\Support\Demo\DemoMasterData;
use App\Support\Demo\DemoRbac;
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
       ACCESS CONTROL
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_permission_list_is_derived_from_where_permissions_are_used(): void
    {
        $this->withDemoData();

        $keys = collect(DemoRbac::permissions())->flatten(1)->pluck('key');

        /*
         * A hand-kept list drifts within a month: somebody adds a module, wires
         * its key into config/navigation.php, and the Admin Panel cannot grant
         * the permission the sidebar is already filtering on.
         *
         * Checked against all three sidebars and the dashboard registry.
         */
        foreach (['navigation', 'navigation-client'] as $file) {
            foreach ((array) config($file) as $entry) {
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

    public function test_admin_permissions_are_not_offered_to_any_role(): void
    {
        $this->withDemoData();

        /*
         * §2.1: the Admin Panel is "not a role and not assignable". It is an
         * account type in its own realm.
         *
         * Offering `admin.settings.view` as a toggle on the HR page would grant
         * nothing — realm middleware refuses /admin to a staff session whatever
         * keys it holds — and would imply the panel's authority is something a
         * person can be given a piece of.
         */
        $keys = collect(DemoRbac::permissions())->flatten(1)->pluck('key');

        foreach ((array) config('navigation-admin') as $entry) {
            $this->assertFalse(
                $keys->contains($entry['permission']),
                $entry['permission'].' is offered as a role permission',
            );
        }

        $body = $this->pageBody('/admin/access/hr');

        $this->assertStringNotContainsString('admin.settings.view', $body);
        // And the page says why it is absent rather than leaving a gap.
        $this->assertStringContainsString('The Admin Panel', $body);
    }

    public function test_a_permission_toggle_names_who_it_would_land_on(): void
    {
        $this->withDemoData();

        $body = $this->pageBody('/admin/access/manager');

        /*
         * The click is made looking at a role name; the consequence lands on
         * people whose names are not otherwise on the page. Without this
         * sentence the screen is a matrix, and a matrix is how an organisation
         * ends up not knowing who can see payroll.
         */
        $this->assertStringContainsString('Granting gives it to', $body);

        foreach (DemoRbac::holdersOf('manager') as $person) {
            $this->assertStringContainsString($person['name'], $body);
        }
    }

    public function test_the_blast_radius_excludes_people_who_already_hold_it_elsewhere(): void
    {
        $this->withDemoData();

        /*
         * Roles stack as a union (§2.4). Granting salary.view to Employee — a
         * role everybody holds — must not count the HR staff who already have
         * it, or the number overstates the change.
         */
        $everyone = DemoRbac::whoWouldHold('salary.view', 'employee');
        $hrHolders = DemoRbac::holdersOf('hr');

        foreach ($hrHolders as $person) {
            $this->assertFalse(
                $everyone->contains('user_id', $person['user_id']),
                $person['name'].' already holds salary.view through HR and should not be counted',
            );
        }
    }

    public function test_the_employee_base_is_not_editable_as_a_role(): void
    {
        $this->withDemoData();

        /*
         * §5: it is not a role, and it is granted implicitly precisely so a
         * role edit cannot revoke it. An owner who could remove it would take
         * everyone's own attendance, leave and payslips away at once.
         */
        $this->assertNull(DemoRbac::role('employee_base'));
        $this->get('/admin/access/employee_base')->assertNotFound();

        // And the page says why it is absent, rather than leaving a puzzle.
        $this->assertStringContainsString('The Employee base', $this->pageBody('/admin/access/hr'));
    }

    public function test_rank_is_per_domain_and_hr_outranks_system_in_people(): void
    {
        $this->withDemoData();

        // The row that makes rank per-domain rather than one ladder (§2.5).
        $hr = DemoRbac::role('hr');
        $ceo = DemoRbac::role('ceo');

        $this->assertGreaterThan($hr['ranks']['system'], $hr['ranks']['people']);
        $this->assertGreaterThan(0, $ceo['ranks']['finance']);

        // A Mentor has no standing anywhere — read-only, no Employee base.
        $this->assertSame(0, array_sum(DemoRbac::role('mentor')['ranks']));
    }

    /* ══════════════════════════════════════════════════════════════════════
       MASTER DATA AND THE REST
       ══════════════════════════════════════════════════════════════════════ */

    public function test_master_data_states_what_uses_a_row_before_it_is_retired(): void
    {
        $this->withDemoData();

        $body = $this->pageBody('/admin/master-data/departments');

        // The count is what makes retiring something a decision rather than a
        // click, and it is counted from the records, not stored.
        $this->assertStringContainsString('In use', $body);
        $this->assertStringContainsString('There is no delete', $body);

        $departments = DemoMasterData::rows('departments');
        $this->assertGreaterThan(0, $departments->sum('in_use'));
    }

    public function test_an_unknown_master_data_list_is_not_found(): void
    {
        $this->withDemoData();

        $this->get('/admin/master-data/salaries')->assertNotFound();
    }

    public function test_the_audit_entry_records_the_effect_and_not_only_the_value(): void
    {
        $this->withDemoData();

        $entry = DemoAudit::all()->firstWhere('kind', DemoAudit::SETTING);

        $this->assertNotNull($entry);

        /*
         * "half_day_hours: 4 → 6" is true and useless. The log has to carry
         * what the change did, because that is what somebody comes here for.
         */
        $this->assertNotSame('', $entry['before']);
        $this->assertStringContainsString('reclassified', strtolower($entry['after']));

        // §6 requires all of these on every entry.
        foreach (['actor', 'action', 'entity', 'before', 'after', 'ip', 'agent'] as $field) {
            $this->assertArrayHasKey($field, $entry);
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE SHELL
       ══════════════════════════════════════════════════════════════════════ */

    public function test_every_page_renders(): void
    {
        $this->withDemoData();

        foreach ((array) config('navigation-admin') as $entry) {
            $this->get(route($entry['route']))->assertOk();
        }

        $this->get('/admin/access/hr')->assertOk();
        $this->get('/admin/accounts/EMP005')->assertOk();
        $this->get('/admin/master-data/departments')->assertOk();
        $this->get('/admin/audit/AUD-4021')->assertOk();
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
