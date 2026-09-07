<?php

namespace Tests\Feature;

use App\Support\Demo\DemoLeave;
use App\Support\LeavePolicy;
use App\Support\LeavePresenter as P;
use Tests\TestCase;

class LeavePageTest extends TestCase
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
    protected function withDemoData(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);
    }

    public function test_the_four_pages_render(): void
    {
        $this->withDemoData();

        $this->get('/leave')->assertOk()->assertSee('Leave Requests', false);
        $this->get('/leave/mine')->assertOk()->assertSee('My Leave', false);
        $this->get('/leave/request')->assertOk()->assertSee('Request leave', false);
        $this->get('/leave/LV-2026-039')->assertOk()->assertSee('Family holiday', false);
    }

    public function test_mine_and_request_are_not_read_as_request_references(): void
    {
        $this->withDemoData();

        $this->get('/leave/mine')->assertOk();
        $this->get('/leave/request')->assertOk();
    }

    public function test_the_queue_defaults_to_what_is_undecided(): void
    {
        // The page exists to clear a queue, so "All" is the wrong landing.
        $this->withDemoData();

        $html = $this->get('/leave')->getContent();

        foreach (DemoLeave::pending() as $request) {
            $this->assertStringContainsString($request['id'], $html);
        }

        $decided = DemoLeave::all()->firstWhere('status', P::APPROVED);
        $this->assertStringNotContainsString($decided['id'], $html);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE DECISION
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_queue_offers_no_approve_or_reject_in_a_row(): void
    {
        // The handover had a tick and a cross as unlabelled icon buttons thirty
        // pixels apart, each of which is somebody's holiday. Deciding happens on
        // the request, where the dates, reason, balance and clashes are all on
        // screen.
        $this->withDemoData();

        $html = $this->get('/leave')->getContent();

        $this->assertStringNotContainsString(route('leave.approve', ['leaveRequest' => 'LV-2026-039']), $html);
        $this->assertStringNotContainsString(route('leave.reject', ['leaveRequest' => 'LV-2026-039']), $html);
        $this->assertStringContainsString('Review', $html);
    }

    public function test_approve_and_reject_are_separate_labelled_forms(): void
    {
        $this->withDemoData();

        $html = $this->get('/leave/LV-2026-039')->getContent();

        $this->assertStringContainsString(route('leave.approve', ['leaveRequest' => 'LV-2026-039']), $html);
        $this->assertStringContainsString(route('leave.reject', ['leaveRequest' => 'LV-2026-039']), $html);
        $this->assertStringContainsString('Approve 5 days', $html);
        $this->assertStringContainsString('Reject', $html);
    }

    public function test_rejecting_asks_for_a_reason(): void
    {
        // "Rejected" with no explanation is the version people have to chase in
        // person. The handover's reject button captured nothing.
        $this->withDemoData();

        $html = $this->get('/leave/LV-2026-039')->getContent();

        $this->assertStringContainsString('If you are rejecting, say why', $html);
        $this->assertStringContainsString('name="note"', $html);
    }

    public function test_a_rejected_request_shows_the_reason_it_was_rejected(): void
    {
        $this->withDemoData();

        $this->get('/leave/LV-2026-032')->assertSee('Client review was scheduled', false);
    }

    public function test_nobody_can_decide_their_own_request(): void
    {
        // The one rule in this module that cannot be delegated away. An approver
        // who can grant themselves leave makes the whole record meaningless.
        $this->withDemoData();

        $own = DemoLeave::all()
            ->where('employee', DemoLeave::VIEWER)
            ->firstWhere('status', P::PENDING);

        $this->assertNotNull($own, 'no pending request by the viewer to prove the rule against');

        // Pending, so it *is* decidable in principle …
        $this->assertTrue(P::isDecidable($own));

        // … but not by the person who asked, and the page offers no decision.
        $html = $this->get('/leave/'.$own['id'])->getContent();

        $this->assertStringNotContainsString('Your decision', $html);
        $this->assertStringNotContainsString(route('leave.approve', ['leaveRequest' => $own['id']]), $html);
        $this->assertStringNotContainsString(route('leave.reject', ['leaveRequest' => $own['id']]), $html);
    }

    public function test_someone_elses_pending_request_does_offer_a_decision(): void
    {
        // The counterpart to the test above: the guard must be about who is
        // asking, not about deciding being switched off everywhere.
        $this->withDemoData();

        $theirs = DemoLeave::pending()->firstWhere('employee', '!=', DemoLeave::VIEWER);

        $this->assertNotNull($theirs);
        $this->get('/leave/'.$theirs['id'])->assertSee('Your decision', false);
    }

    public function test_a_decided_request_offers_no_decision(): void
    {
        $this->withDemoData();

        foreach (['LV-2026-036', 'LV-2026-032', 'LV-2026-031'] as $id) {
            $this->assertStringNotContainsString('Your decision', $this->get('/leave/'.$id)->getContent());
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHO ELSE IS OFF — THE THING THE HANDOVER LEFT OUT
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_request_shows_who_else_is_off_across_those_dates(): void
    {
        $this->withDemoData();

        $request = DemoLeave::find('LV-2026-039');
        $clashes = DemoLeave::clashesWith($request);

        $this->assertNotEmpty($clashes, 'no sample clash to prove the point');

        $html = $this->get('/leave/LV-2026-039')->getContent();
        $this->assertStringContainsString('Also away on these dates', $html);

        foreach ($clashes as $clash) {
            $this->assertStringContainsString($clash['employee_record']['name'], $html);
        }
    }

    public function test_clashes_include_pending_requests_not_only_granted_ones(): void
    {
        // Two people asking for the same week is exactly the clash worth
        // catching before either is approved.
        $this->withDemoData();

        $clashes = DemoLeave::clashesWith(DemoLeave::find('LV-2026-039'));

        $this->assertContains(P::PENDING, $clashes->pluck('status')->all());
    }

    public function test_a_clash_never_includes_the_request_itself(): void
    {
        $this->withDemoData();

        foreach (DemoLeave::all() as $request) {
            $this->assertNotContains($request['id'], DemoLeave::clashesWith($request)->pluck('id')->all());
        }
    }

    public function test_rejected_and_withdrawn_requests_are_not_counted_as_clashes(): void
    {
        // Nobody is off on a day they were refused.
        $this->withDemoData();

        foreach (DemoLeave::all() as $request) {
            foreach (DemoLeave::clashesWith($request) as $clash) {
                $this->assertContains($clash['status'], [P::APPROVED, P::PENDING]);
            }
        }
    }

    public function test_the_queue_shows_who_is_away_in_the_next_fortnight(): void
    {
        $this->withDemoData();

        $this->get('/leave')->assertSee('Away in the next two weeks', false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       PERSONAL DATA
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_reason_and_contact_number_are_not_columns_on_any_list(): void
    {
        // "Fever, seeing a doctor tomorrow" is health information, and a phone
        // number is personal data (§6). Both belong on the request, for the
        // person deciding it — not on a page listing twelve people.
        $this->withDemoData();

        foreach (['/leave', '/leave?tab=all', '/leave/mine'] as $url) {
            $html = $this->get($url)->getContent();

            foreach (DemoLeave::all() as $request) {
                $this->assertStringNotContainsString($request['reason'], $html, "a reason appears on {$url}");

                if ($request['contact']) {
                    $this->assertStringNotContainsString($request['contact'], $html, "a contact number appears on {$url}");
                }
            }
        }
    }

    public function test_the_reason_and_contact_do_appear_on_the_request_itself(): void
    {
        $this->withDemoData();

        $response = $this->get('/leave/LV-2026-040');
        $response->assertSee('Fever, seeing a doctor tomorrow', false);
        $response->assertSee('+91 98200 44556', false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       BALANCE AND POLICY
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_balance_shown_reconciles_with_the_policy(): void
    {
        // The handover granted 34 days, said 12 taken, and showed 18 left.
        $this->withDemoData();

        $balance = LeavePolicy::balance(DemoLeave::forEmployee(DemoLeave::VIEWER));

        $this->assertSame($balance['entitlement'] - $balance['taken'], $balance['remaining']);
        $this->assertSame(LeavePolicy::totalEntitlement(), $balance['entitlement']);

        $response = $this->get('/leave/mine');
        $response->assertSee((string) $balance['remaining'], false);
        $response->assertSee('Of '.$balance['entitlement'].' days granted', false);
    }

    public function test_the_page_states_the_balance_is_for_this_year_only(): void
    {
        // Rather than an "Expired" tile whose rule does not exist, which the
        // handover showed reading zero.
        $this->withDemoData();

        $html = $this->get('/leave/mine')->getContent();

        $this->assertStringContainsString('has not been settled', $html);
        $this->assertStringNotContainsStringIgnoringCase('Expired Leaves', $html);
    }

    public function test_unpaid_leave_is_never_given_a_balance(): void
    {
        $this->withDemoData();

        foreach (['/leave/mine', '/leave/request'] as $url) {
            $html = $this->get($url)->getContent();
            $this->assertStringNotContainsString('id="bal-unpaid"', $html);
        }

        $this->get('/leave')->assertSee('No allowance', false);
    }

    public function test_the_policy_card_reads_from_configuration(): void
    {
        $this->withDemoData();

        $html = $this->get('/leave')->getContent();

        foreach (LeavePolicy::types() as $meta) {
            $this->assertStringContainsString($meta['label'], $html);
        }

        $this->assertStringContainsString('Set in the Admin Panel', $html);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE USUAL GUARDS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_demo_source_is_inert_outside_local_debug(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);

        $this->assertFalse(DemoLeave::enabled());
        $this->assertTrue(DemoLeave::all()->isEmpty());
        $this->assertTrue(DemoLeave::upcomingAbsences()->isEmpty());
    }

    public function test_no_figure_is_written_into_the_markup(): void
    {
        // The handover hardcoded 8 / 25 / 6 / 3 / 42 and 18 / 12 / 5 / 0 / 35.
        $this->withDemoData();

        $response = $this->get('/leave');
        $response->assertSee('Waiting on you', false);
        $response->assertDontSee('>42<', false);
    }

    public function test_the_tabs_filter_the_queue(): void
    {
        $this->withDemoData();

        $approved = $this->get('/leave?tab=approved');
        $approved->assertSee('LV-2026-036', false);
        $approved->assertDontSee('LV-2026-039', false);
    }

    public function test_an_invalid_tab_or_filter_is_rejected(): void
    {
        $this->get('/leave?tab=whatever')->assertSessionHasErrors('tab');
        $this->get('/leave?type=holiday')->assertSessionHasErrors('type');
    }

    public function test_an_unknown_request_is_not_found(): void
    {
        $this->withDemoData();

        $this->get('/leave/LV-9999-999')->assertNotFound();
        $this->get('/leave/'.urlencode('<script>'))->assertNotFound();
    }

    public function test_the_write_routes_exist_so_the_forms_are_real(): void
    {
        $this->assertTrue(app('router')->has('leave.store'));
        $this->assertTrue(app('router')->has('leave.approve'));
        $this->assertTrue(app('router')->has('leave.reject'));
        $this->assertTrue(app('router')->has('leave.cancel'));
    }

    public function test_approving_and_rejecting_are_separate_routes(): void
    {
        // One endpoint taking a decision parameter is one place for a default
        // to be wrong.
        $approve = app('router')->getRoutes()->getByName('leave.approve');
        $reject = app('router')->getRoutes()->getByName('leave.reject');

        $this->assertNotSame($approve->uri(), $reject->uri());
    }

    public function test_no_route_deletes_a_leave_request(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'leave')) {
                $this->assertNotContains('DELETE', $route->methods(), "a DELETE route exists at {$route->uri()}");
            }
        }
    }

    public function test_the_pages_render_nothing_the_content_security_policy_would_block(): void
    {
        // The handover wired its tabs with an inline <script> and coloured its
        // donut with style="--dot:#3B82F6" — none of it would have worked.
        $this->withDemoData();

        foreach (['/leave', '/leave/mine', '/leave/request', '/leave/LV-2026-039'] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), "inline <style> in {$url}");
            $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), "inline style attribute in {$url}");
            $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), "inline event handler in {$url}");
            $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), "inline <script> in {$url}");
        }
    }

    public function test_the_sidebar_marks_leave_as_current(): void
    {
        $this->withDemoData();

        foreach (['/leave', '/leave/mine', '/leave/request', '/leave/LV-2026-039'] as $url) {
            $this->assertSame(
                1,
                substr_count($this->get($url)->getContent(), 'class="sb-link active"'),
                "sidebar current marker wrong on {$url}"
            );
        }
    }
}
