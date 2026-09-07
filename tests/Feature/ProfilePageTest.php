<?php

namespace Tests\Feature;

use App\Support\Demo\DemoLeave;
use App\Support\Demo\DemoProfile;
use App\Support\Demo\DemoProjects;
use App\Support\Demo\DemoTickets;
use App\Support\LeavePolicy;
use App\Support\ProfilePolicy;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Every route in the staff realm is behind `realm:staff` now (§3.1), so
         * a page test has to be somebody. A CEO, because this file is about
         * what the page renders rather than about who may see it — the guard
         * and the permission filtering have their own tests.
         */
        $this->signInAsStaff();
    }
    /** @var list<string> */
    protected const PAGES = ['/profile', '/profile/preferences', '/profile/password', '/profile/activity'];

    protected function withDemoData(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);
    }

    public function test_the_four_pages_render(): void
    {
        $this->withDemoData();

        $this->get('/profile')->assertOk()->assertSee('My Profile', false);
        $this->get('/profile/preferences')->assertOk()->assertSee('What the company sees', false);
        $this->get('/profile/password')->assertOk()->assertSee('Change your password', false);
        $this->get('/profile/activity')->assertOk()->assertSee('What has happened to your account', false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHO OWNS WHICH FIELD — THE DECISION THIS MODULE IS ABOUT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_nothing_hr_owns_is_an_input_on_any_profile_page(): void
    {
        // The handover had Full Name, Email Address and Date of Birth as
        // editable text boxes in this exact form. A person who can set their own
        // department can grant themselves whatever that department can see, and
        // one who can rename themselves detaches their own history from them.
        $this->withDemoData();

        $locked = array_merge(
            ProfilePolicy::fieldsOwnedBy(ProfilePolicy::HR),
            ProfilePolicy::fieldsOwnedBy(ProfilePolicy::SYSTEM),
        );

        $this->assertNotEmpty($locked);

        foreach (self::PAGES as $url) {
            $html = $this->get($url)->getContent();

            foreach ($locked as $field) {
                $this->assertStringNotContainsString(
                    'name="'.$field.'"',
                    $html,
                    "{$field} is submittable on {$url} — it is not the person's to change",
                );
            }
        }
    }

    public function test_the_write_allow_list_holds_none_of_them(): void
    {
        // The rule that actually protects the record. `disabled` in the markup
        // is a rendering instruction; the browser is not where this lives.
        $editable = ProfilePolicy::selfEditable();

        foreach (['name', 'department', 'designation', 'reports_to', 'dob', 'role', 'employee_id', 'joined', 'email', 'last_login'] as $field) {
            $this->assertNotContains($field, $editable, "{$field} is in the self-editable allow-list");
        }
    }

    public function test_an_unknown_field_defaults_to_not_being_the_persons(): void
    {
        // The safe default for "who may change this" is somebody other than the
        // subject of it, so a field added without a decision is locked rather
        // than open.
        $this->assertSame(ProfilePolicy::HR, ProfilePolicy::ownerOf('salary_band'));
        $this->assertFalse(ProfilePolicy::isSelfEditable('salary_band'));
    }

    public function test_every_locked_field_says_why_and_offers_a_route_out(): void
    {
        // A read-only field with no way to correct it is a dead end, and the
        // first thing somebody does about it is ask.
        $this->withDemoData();

        $html = $this->get('/profile')->getContent();

        foreach (ProfilePolicy::fieldsOwnedBy(ProfilePolicy::HR) as $field) {
            $this->assertNotSame('', ProfilePolicy::whyOf($field), "{$field} is locked with no reason given");
        }

        $this->assertStringContainsString('Not yours to change', $html);
        $this->assertStringContainsString(route(ProfilePolicy::correctionRoute()), $html);
    }

    public function test_the_things_that_are_the_persons_own_really_are_editable(): void
    {
        // The counterpart: locking everything would be safe and useless. Nobody
        // should raise a ticket to correct their own phone number.
        $this->withDemoData();

        $html = $this->get('/profile')->getContent();

        foreach (['phone', 'address', 'emergency_name', 'emergency_phone', 'skills', 'languages'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html, "{$field} should be the person's own");
            $this->assertTrue(ProfilePolicy::isSelfEditable($field));
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE CREDENTIALS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_email_is_never_a_text_box(): void
    {
        // It is the login identifier (§4.1). A field that writes it straight to
        // the record is an account-takeover primitive: point the account at
        // another address and the owner is locked out of a system that no longer
        // knows how to reach them.
        $this->withDemoData();

        foreach (self::PAGES as $url) {
            $html = $this->get($url)->getContent();

            $this->assertSame(0, preg_match_all('/<input[^>]*name="email"/i', $html), "an email input on {$url}");
        }

        $this->assertSame(ProfilePolicy::VERIFIED, ProfilePolicy::ownerOf('email'));
    }

    public function test_changing_the_email_is_a_flow_that_starts_with_a_button(): void
    {
        $this->withDemoData();

        $response = $this->get('/profile/password');

        $response->assertSee(route('profile.email.change'), false);
        $response->assertSee('Start an email change', false);
        $response->assertSee('confirming from both the old address and the new one', false);
    }

    public function test_changing_the_password_asks_for_the_current_one(): void
    {
        // A borrowed unlocked laptop is the whole threat, and this field is the
        // only thing standing in front of it.
        $this->withDemoData();

        $html = $this->get('/profile/password')->getContent();

        $this->assertStringContainsString('name="current_password"', $html);
        $this->assertStringContainsString('name="password"', $html);
        $this->assertStringContainsString('name="password_confirmation"', $html);
    }

    public function test_the_password_policy_is_stated_including_what_it_does_not_ask_for(): void
    {
        // §4.7. A password field that does not demand a capital and a symbol
        // looks broken to anybody used to ones that do.
        $this->withDemoData();

        $response = $this->get('/profile/password');

        $response->assertSee('At least twelve characters', false);
        $response->assertSee('No composition rules', false);
        $response->assertSee('No expiry', false);
    }

    public function test_no_page_nags_about_password_age(): void
    {
        // §4.7 is explicit that there is no forced rotation. Reporting the age
        // is a fact; asking somebody to change a fine password is a nag that
        // produces weaker ones.
        $this->withDemoData();

        foreach (self::PAGES as $url) {
            $html = $this->get($url)->getContent();

            $this->assertStringNotContainsStringIgnoringCase('password expires', $html);
            $this->assertStringNotContainsStringIgnoringCase('time to change your password', $html);
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       IT IS ALWAYS YOUR OWN PROFILE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_no_profile_route_takes_an_employee(): void
    {
        // Resolved from the session, so there is no identifier to change to
        // somebody else's. Viewing a colleague's record is `employees.show`,
        // which is a different page with different rules.
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'profile')) {
                continue;
            }

            foreach ($route->parameterNames() as $parameter) {
                $this->assertSame('document', $parameter, "profile route {$route->uri()} takes {$parameter}");
            }
        }
    }

    public function test_the_activity_log_is_the_viewers_own_only(): void
    {
        $this->withDemoData();

        $this->assertTrue(DemoProfile::activity('EMP007')->isEmpty());
        $this->assertTrue(DemoProfile::documents('EMP007')->isEmpty());
        $this->assertFalse(DemoProfile::activity(DemoProfile::VIEWER)->isEmpty());
    }

    public function test_the_activity_log_claims_no_location(): void
    {
        // The handover's read "New login from Kolkata, IN" — IP geolocation that
        // does not exist and was never decided. This is the screen somebody
        // checks when they think their account has been used by someone else,
        // and a wrong city sends them chasing nothing.
        $this->withDemoData();

        $html = $this->get('/profile/activity')->getContent();

        $this->assertStringNotContainsStringIgnoringCase('from Kolkata', $html);
        $this->assertStringNotContainsStringIgnoringCase('IP address', $html);
    }

    /* ══════════════════════════════════════════════════════════════════════
       PERSONAL DATA
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_birth_year_never_reaches_the_page(): void
    {
        // The same rule the birthday board holds to — a day and a month, never
        // the year. It applies on a person's own profile too, because a
        // screenshot of this page is still a screenshot.
        $this->withDemoData();

        $profile = DemoProfile::find();
        $year = \Illuminate\Support\Carbon::parse($profile['dob'])->format('Y');

        foreach (self::PAGES as $url) {
            $this->assertStringNotContainsString(
                \Illuminate\Support\Carbon::parse($profile['dob'])->format('d M Y'),
                $this->get($url)->getContent(),
                "a full date of birth on {$url}",
            );
        }

        $this->assertStringContainsString(
            \Illuminate\Support\Carbon::parse($profile['dob'])->format('d F'),
            $this->get('/profile')->getContent(),
        );
        $this->assertNotSame('', $year);
    }

    public function test_documents_are_reached_through_a_route_and_never_a_static_path(): void
    {
        // A PAN or Aadhaar scan at a guessable path under the webroot is a link
        // that works for anyone who tries it, forever, with no session involved.
        $this->withDemoData();

        $html = $this->get('/profile')->getContent();

        foreach (DemoProfile::documents() as $document) {
            $this->assertStringContainsString(
                route('profile.documents.download', ['document' => $document['id']]),
                $html,
            );
        }

        $this->assertStringNotContainsString('/storage/', $html);
        $this->assertStringContainsString('every download is logged', $html);
    }

    public function test_no_document_is_previewed_or_thumbnailed(): void
    {
        // A thumbnail is the document, smaller.
        $this->withDemoData();

        $html = $this->get('/profile')->getContent();

        $this->assertSame(0, preg_match_all('/<img[^>]*document/i', $html));
        $this->assertSame(0, preg_match_all('/<iframe/i', $html));
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE MILESTONE OPT-OUT FINALLY HAS A SURFACE
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_milestone_opt_out_is_reachable(): void
    {
        // Announcements decided (2026-08-28) that anyone may opt out of their
        // own birthday post, and `announce_milestones` has existed on the
        // employee record since with nowhere to set it. A per-person opt-out
        // nobody can reach is not an opt-out; it is a column.
        $this->withDemoData();

        $response = $this->get('/profile/preferences');

        $response->assertSee('name="announce_milestones"', false);
        $response->assertSee('Announce my birthday and work anniversary', false);
        $this->assertTrue(ProfilePolicy::isSelfEditable('announce_milestones'));
    }

    public function test_the_opt_out_reflects_the_stored_value(): void
    {
        $this->withDemoData();

        $profile = DemoProfile::find();
        $html = $this->get('/profile/preferences')->getContent();

        // Matched on the input tag rather than an exact attribute string, so the
        // test is about the state being reflected and not about the order Blade
        // happens to write attributes in.
        preg_match('/<input[^>]*name="announce_milestones"[^>]*>/i', $html, $input);

        $this->assertNotEmpty($input, 'the opt-out is not on the page at all');
        $this->assertSame(
            $profile['announce_milestones'],
            str_contains($input[0], 'checked'),
            'the checkbox does not reflect the stored value',
        );
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE SUMMARY IS READ, NOT INVENTED
       ══════════════════════════════════════════════════════════════════════ */

    public function test_every_summary_figure_comes_from_the_module_that_owns_it(): void
    {
        // The handover hardcoded 15 / 128 / 42 / "22 of 22" / 12 days. A summary
        // that disagrees with the page it summarises is worse than no summary.
        $this->withDemoData();

        $summary = collect(DemoProfile::summary())->keyBy('label');

        $this->assertSame(
            (string) DemoProjects::mine(DemoProfile::VIEWER)->count(),
            $summary['Projects you are on']['value'],
        );
        $this->assertSame(
            (string) DemoTickets::raisedBy(DemoProfile::VIEWER)->count(),
            $summary['Tickets you raised']['value'],
        );

        $balance = LeavePolicy::balance(DemoLeave::forEmployee(DemoProfile::VIEWER));
        $this->assertSame(
            $balance['remaining'].' of '.$balance['entitlement'].' days',
            $summary['Leave left this year']['value'],
        );
    }

    public function test_every_summary_row_links_to_the_page_it_came_from(): void
    {
        // A number somebody disputes should be one click from the page that
        // produced it — which is also what stops this card becoming a second
        // source of truth.
        $this->withDemoData();

        $html = $this->get('/profile')->getContent();

        foreach (DemoProfile::summary() as $row) {
            $this->assertStringContainsString(route($row['route']), $html);
        }
    }

    public function test_the_handovers_invented_figures_are_gone(): void
    {
        $this->withDemoData();

        $response = $this->get('/profile');

        $response->assertDontSee('>128<', false);
        $response->assertDontSee('22 / 22', false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE USUAL GUARDS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_demo_source_is_inert_outside_local_debug(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);

        $this->assertFalse(DemoProfile::enabled());
        $this->assertNull(DemoProfile::find());
        $this->assertSame([], DemoProfile::summary());
        $this->assertTrue(DemoProfile::documents()->isEmpty());
        $this->assertTrue(DemoProfile::activity()->isEmpty());
    }

    public function test_the_write_routes_exist_so_the_forms_are_real(): void
    {
        foreach ([
            'profile.update', 'profile.preferences.update', 'profile.password.update',
            'profile.email.change', 'profile.photo', 'profile.documents.store',
            'profile.documents.download',
        ] as $name) {
            $this->assertTrue(app('router')->has($name), "{$name} is missing");
        }
    }

    public function test_no_route_writes_a_field_hr_owns(): void
    {
        // There is no `profile.role`, no `profile.department`. A route that let
        // somebody set their own designation would make the record meaningless.
        foreach (ProfilePolicy::fieldsOwnedBy(ProfilePolicy::HR) as $field) {
            $this->assertFalse(app('router')->has('profile.'.$field));
        }
    }

    public function test_the_tabs_are_links_with_their_own_urls(): void
    {
        // The handover's were <button>s switched by an inline <script>, which
        // our CSP blocks — they would not have switched at all.
        $this->withDemoData();

        $html = $this->get('/profile')->getContent();

        foreach (['profile.show', 'profile.preferences', 'profile.password', 'profile.activity'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'"', $html);
        }
    }

    public function test_the_pages_render_nothing_the_content_security_policy_would_block(): void
    {
        $this->withDemoData();

        foreach (self::PAGES as $url) {
            $html = $this->get($url)->getContent();

            $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), "inline <style> in {$url}");
            $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), "inline style attribute in {$url}");
            $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), "inline event handler in {$url}");
            $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), "inline <script> in {$url}");
        }
    }

    public function test_the_sidebar_marks_the_profile_as_current(): void
    {
        $this->withDemoData();

        foreach (self::PAGES as $url) {
            $this->assertSame(
                1,
                substr_count($this->get($url)->getContent(), 'class="sb-link active"'),
                "sidebar current marker wrong on {$url}",
            );
        }
    }
}
