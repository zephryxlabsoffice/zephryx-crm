<?php

namespace Tests\Feature;

use App\Support\Dashboard\DashboardComposer;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\Rbac;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The dashboard.
 *
 * The thing worth testing here is not that a card renders — it is that the page
 * is COMPOSED, and that composition can only ever subtract. Five hand-written
 * per-role pages would need five sets of these assertions and would still not
 * answer what a Manager + HR sees.
 */
class DashboardPageTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * ─────────────────────────────────────────────────────────────────────
         * A SEEDED PERSON, NOT A BARE ACCOUNT
         *
         * Every route in the staff realm is behind `realm:staff` (§3.1), so a
         * page test has to be somebody. It now has to be somebody with an
         * EMPLOYMENT RECORD as well: the personal widgets read the viewer's own
         * tasks, leave, attendance and pay, and a `User` with no `employees`
         * row would render every one of them empty — passing the assertions
         * about what is hidden and proving nothing about what is shown.
         *
         * The dashboard used to paper over exactly this with a fallback to a
         * fixed demo employee. That fallback is gone, and this is what
         * replaced it.
         * ─────────────────────────────────────────────────────────────────────
         */
        $this->seedDemoWorkforce();

        /*
         * A CEO, because this file is about what the page RENDERS rather than
         * about who may see it — the guard and the permission filtering have
         * their own tests, and the preview below can only narrow what this
         * account already holds.
         *
         * With an employment record attached, which `signInAsStaff` does not
         * create: `staff_kind` of `employee` and no `employees` row is a state
         * the application never produces, and it made every personal widget
         * render empty.
         */
        $user = $this->signInAsStaff(['ceo']);

        Employee::create([
            'user_id' => $user->id,
            'joined_on' => Carbon::today()->subYear(),
        ]);
    }

    protected function withDemoData(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);
    }

    protected function signInAsSeeded(string $staffId): User
    {
        $user = User::where('user_id', $staffId)->firstOrFail();

        app(Rbac::class)->forget($user);

        return tap($user, fn (User $u) => $this->actingAs($u));
    }

    public function test_it_renders(): void
    {
        $this->withDemoData();

        $this->get('/dashboard')->assertOk();
    }

    public function test_it_greets_the_viewer_by_their_first_name(): void
    {
        // The name comes from the ACCOUNT, not the employment record, so a
        // Mentor — who has no record — is still greeted properly.
        $this->signInAsSeeded('EMP002');

        $body = $this->pageBody('/dashboard');

        // First name only. "Good morning, Amit Verma" is how a bank speaks.
        $this->assertMatchesRegularExpression('/Good (morning|afternoon|evening), Amit</', $body);
        $this->assertStringNotContainsString('Amit Verma</h1>', $body);
    }

    public function test_every_role_composes_a_page_that_renders(): void
    {
        $this->withDemoData();

        $roles = Role::pluck('role_key');

        $this->assertNotEmpty($roles);

        // Driven off the roles table, so a role added there cannot skip the
        // check — the preview reads the real definitions now.
        foreach ($roles as $role) {
            $this->get('/dashboard?as='.$role)->assertOk();
        }
    }

    public function test_a_role_sees_only_the_widgets_its_permissions_reveal(): void
    {
        $this->withDemoData();

        $employee = $this->pageBody('/dashboard?as=employee');
        $hr = $this->pageBody('/dashboard?as=hr');

        // HR decides leave; an employee does not, and the queue is the widget
        // that says so.
        $this->assertStringContainsString('Leave to decide', $hr);
        $this->assertStringNotContainsString('Leave to decide', $employee);

        /*
         * Both hold the Employee base, so both get the punch card — HR has
         * their own attendance too, which is precisely why they cannot correct
         * their own record (§2.6).
         *
         * Asserted on the card rather than on the check-in button: which button
         * the card shows depends on whether the viewer has already checked in
         * today, so a test that looked for one would pass or fail on the demo
         * clock rather than on the permission.
         */
        $this->assertStringContainsString('Hours so far', $employee);
        $this->assertStringContainsString('Hours so far', $hr);
    }

    public function test_a_widget_the_viewer_cannot_see_has_its_data_left_unread(): void
    {
        $this->withDemoData();

        $employee = $this->pageBody('/dashboard?as=employee');

        /*
         * Payroll is HR's and the CEO's. The assertion is deliberately about
         * the FIGURES and not just the card: DashboardData is never asked about
         * a widget that did not survive the filter, so a total nobody may see
         * is never assembled, let alone rendered and hidden with CSS.
         */
        $this->assertStringNotContainsString('Payroll', $employee);
        $this->assertStringNotContainsString('Awaiting payment', $employee);
        $this->assertStringNotContainsString('Paid out', $employee);
    }

    public function test_a_mentor_gets_no_personal_widgets_at_all(): void
    {
        $this->withDemoData();

        $body = $this->pageBody('/dashboard?as=mentor');

        /*
         * §2.1 — a Mentor has no Employee base and no personal records at all.
         * Every widget below is one this application would otherwise head with
         * the word "Your", and a Mentor has no such thing to show.
         *
         * If any of these appears, a widget has been given a `*.view` key where
         * it needed a `*.self` one — the distinction config/dashboard.php turns
         * on.
         */
        foreach (['Hours so far', 'Your pay', 'Your leave', 'Your tasks', 'Your meetings', 'Your team'] as $personal) {
            $this->assertStringNotContainsString($personal, $body, 'a Mentor was shown "'.$personal.'"');
        }

        // What a Mentor does keep: read-only sight of the company. The board is
        // published to everyone, so an empty rail would be wrong too.
        $this->assertStringContainsString('Announcements', $body);
    }

    public function test_the_preview_can_only_narrow_what_the_gate_already_allows(): void
    {
        $this->withDemoData();

        $everything = $this->pageBody('/dashboard');

        /*
         * The gate in development allows everything, so the unfiltered page is
         * the widest the dashboard can ever be. Every role must be a subset of
         * it — a preview that added a widget would be a privilege-escalation
         * query parameter.
         */
        foreach (Role::all() as $role) {
            $body = $this->pageBody('/dashboard?as='.$role->role_key);

            $this->assertLessThanOrEqual(
                substr_count($everything, '<section class="card'),
                substr_count($body, '<section class="card'),
                $role->role_name.' renders more cards than the unfiltered page',
            );
        }
    }

    public function test_an_unknown_preview_role_is_refused_and_never_echoed(): void
    {
        $this->withDemoData();

        // Validated against the registry, so `?as=` cannot put arbitrary text
        // on an authenticated page.
        $response = $this->get('/dashboard?as=%22%3E%3Cscript%3E');

        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertDontSee('<script>', false);
    }

    public function test_the_preview_switcher_does_not_exist_outside_local_debug(): void
    {
        /*
         * It is a development affordance, gated on local + debug, and the
         * whole block disappears with it.
         *
         * Asserted against the page body and on the block's own class: the
         * topbar carries the words "Development preview" as a placeholder role
         * on every page, so the phrase alone proves nothing.
         */
        $this->assertStringNotContainsString('dash-preview', $this->pageBody('/dashboard'));
    }

    public function test_the_kpi_row_stays_one_row(): void
    {
        $this->withDemoData();

        $body = $this->pageBody('/dashboard');

        // Somebody holding every permission still gets a readable row rather
        // than eleven tiles wrapping onto three lines.
        $this->assertLessThanOrEqual(
            DashboardComposer::MAX_KPIS,
            substr_count($body, 'class="kpi-lbl"'),
        );
    }

    public function test_every_registered_widget_has_a_partial_to_render(): void
    {
        /*
         * The registry is the source of truth and the page is a loop over it,
         * so a widget added to configuration with no partial behind it is a
         * runtime error on somebody's dashboard rather than a missing card.
         * Cheaper to catch here.
         */
        $widgets = config('dashboard.widgets');

        $this->assertNotEmpty($widgets);

        foreach ($widgets as $widget) {
            $this->assertTrue(
                view()->exists('dashboard.widgets.'.$widget['key']),
                'no partial for widget '.$widget['key'],
            );
        }
    }

    public function test_every_registry_entry_carries_a_permission_key(): void
    {
        // An entry with no key fails closed in DashboardComposer, which means
        // forgetting one hides a widget rather than showing everyone payroll.
        // This makes the omission loud instead of silent.
        foreach (array_merge(config('dashboard.kpis'), config('dashboard.widgets')) as $entry) {
            $this->assertArrayHasKey('permission', $entry, $entry['key'].' has no permission key');
            $this->assertNotEmpty($entry['permission']);
        }
    }

    public function test_it_renders_nothing_the_content_security_policy_would_block(): void
    {
        $this->withDemoData();

        $html = $this->get('/dashboard')->getContent();

        $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), 'inline <style> block');
        $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), 'inline style attribute');
        $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), 'inline event handler');
        $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), 'inline <script> block');
    }
}
