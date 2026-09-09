<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Demo\DemoTeams;
use Tests\TestCase;

/**
 * Teams — the managing face, the personal face and one team's overview.
 * Foundation spec §12.1.
 */
class TeamsPageTest extends TestCase
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
    /**
     * The demo teams, as real rows.
     *
     * The environment flip this replaced did nothing once the module read the
     * `teams` table: there was no row to find, and a test "passing" against an
     * empty page asserts the empty state while claiming to assert the list.
     */
    protected function withDemoData(): void
    {
        $this->seedDemoWorkforce();
    }

    public function test_the_managing_face_renders(): void
    {
        $response = $this->get('/teams');

        $response->assertOk();
        $response->assertSee('Team Management', false);
        $response->assertSee('class="sidebar"', false);
    }

    public function test_the_personal_face_is_a_separate_page(): void
    {
        // §12.1 — the personal and managing views are distinct pages, not one
        // page with a filter. /teams/mine needs no `teams.view`.
        $response = $this->get('/teams/mine');

        $response->assertOk();
        $response->assertSee('My Teams', false);
        $response->assertSee('Teams you belong to', false);
    }

    public function test_mine_is_not_read_as_a_team_id(): void
    {
        // Route order matters: /teams/{team} would otherwise swallow it.
        $this->get('/teams/mine')->assertOk()->assertSee('My Teams', false);
    }

    public function test_both_faces_show_an_empty_state_with_no_data(): void
    {
        $this->get('/teams')->assertSee('No teams yet.', false);
        $this->get('/teams/mine')->assertSee('You are not in any teams yet.', false);
    }

    public function test_no_figure_is_written_into_the_markup(): void
    {
        // The handover hardcoded 18 / 16 / 2.
        $response = $this->get('/teams');

        $response->assertSee('Total Teams', false);
        $response->assertDontSee('>18<', false);
    }

    public function test_the_demo_source_is_inert_outside_local_debug(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);

        $this->assertFalse(DemoTeams::enabled());
        $this->assertTrue(DemoTeams::all()->isEmpty());
        $this->assertSame([], DemoTeams::activity());
    }

    public function test_it_lists_teams_and_paginates_them(): void
    {
        $this->withDemoData();

        $first = $this->get('/teams');
        $first->assertSee('Design Team', false);
        $first->assertSee('Showing 1 to 8 of 10 teams', false);

        $this->get('/teams?page=2')->assertSee('Showing 9 to 10 of 10 teams', false);
    }

    public function test_search_and_filters_work(): void
    {
        $this->withDemoData();

        $this->get('/teams?q=design')->assertSee('/teams/TM-1001', false);
        $this->get('/teams?q=design')->assertDontSee('/teams/TM-1003', false);

        $this->get('/teams?status=inactive')->assertSee('/teams/TM-1007', false);
        $this->get('/teams?status=inactive')->assertDontSee('/teams/TM-1001', false);
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $this->withDemoData();

        $this->get('/teams?status=;DROP TABLE')->assertSessionHasErrors('status');
    }

    public function test_a_team_without_a_lead_says_so(): void
    {
        // A real state worth seeing, rather than an empty cell.
        $this->withDemoData();

        /*
         * Signed in as somebody who is actually in the lead-less team. The
         * demo source used to answer "my teams" with a hardcoded EMP002 for
         * everybody; the page filters on the viewer's own employment record
         * now, so the test has to be a person who is in one.
         */
        $this->actingAs(User::where('user_id', 'EMP002')->firstOrFail());

        $this->get('/teams/mine')->assertSee('No lead assigned', false);
    }

    public function test_my_teams_is_the_viewers_own_membership(): void
    {
        /*
         * The whole point of the personal face. It takes no parameter — the
         * person comes from the session — so there is nothing here to change to
         * somebody else's teams, the same reason the payslip route takes a
         * period and no employee.
         */
        $this->withDemoData();

        $this->actingAs(User::where('user_id', 'EMP003')->firstOrFail());

        $body = $this->pageBody('/teams/mine');

        // Neha Patel is in Design and Digital Marketing, and not in Backend.
        $this->assertStringContainsString('Digital Marketing Team', $body);
        $this->assertStringNotContainsString('Backend Development Team', $body);
    }

    public function test_somebody_with_no_employment_record_has_no_teams(): void
    {
        // A Mentor holds no Employee base at all (§2.1), so "my teams" is an
        // empty list rather than an error.
        $this->withDemoData();
        $this->signInAsMentor();

        $this->get('/teams/mine')
            ->assertOk()
            ->assertSee('You are not in any teams yet.', false);
    }

    public function test_the_overview_renders_one_team(): void
    {
        $this->withDemoData();

        $response = $this->get('/teams/TM-1001');

        $response->assertOk();
        $response->assertSee('Design Team', false);
        $response->assertSee('TM-1001', false);
        $response->assertSee('Riya Sharma', false);
    }

    public function test_an_unknown_team_is_not_found(): void
    {
        $this->withDemoData();

        $this->get('/teams/TM-9999')->assertNotFound();
        // Anything outside the id pattern never reaches the controller.
        $this->get('/teams/'.urlencode('<script>'))->assertNotFound();
    }

    public function test_the_member_tabs_are_links_with_their_own_urls(): void
    {
        // Bookmarkable, and the back button works. The handover used buttons.
        $this->withDemoData();

        $response = $this->get('/teams/TM-1001');

        $response->assertSee('tab=on_leave', false);
        $response->assertSee('tab=by_department', false);
        $response->assertSee('aria-current="page"', false);
    }

    public function test_the_inactive_tab_filters_members(): void
    {
        $this->withDemoData();

        $response = $this->get('/teams/TM-1001?tab=inactive');

        // EMP012 (Dev Chatterjee) is the inactive member of the Design Team.
        $response->assertSee('/employees/EMP012', false);
        $response->assertDontSee('/employees/EMP001', false);
    }

    public function test_an_empty_tab_says_which_tab_is_empty(): void
    {
        $this->withDemoData();

        $this->get('/teams/TM-1001?tab=on_leave')
            ->assertSee('Nobody in this team is on leave.', false);
    }

    public function test_by_department_groups_rather_than_filters(): void
    {
        $this->withDemoData();

        $grouped = $this->get('/teams/TM-1001?tab=by_department');

        // Every member is still listed — it is a grouping, not a filter.
        $grouped->assertSee('class="group-row"', false);
        $grouped->assertSee('/employees/EMP001', false);
        $grouped->assertSee('/employees/EMP003', false);
    }

    public function test_an_invalid_tab_is_rejected(): void
    {
        $this->withDemoData();

        $this->get('/teams/TM-1001?tab=whatever')->assertSessionHasErrors('tab');
    }

    public function test_the_composition_chart_is_not_the_only_source_of_its_numbers(): void
    {
        $this->withDemoData();

        $html = $this->get('/teams/TM-1001')->getContent();

        $this->assertStringContainsString('class="chart-legend"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function test_the_summary_figures_are_computed(): void
    {
        // The handover had "3.2 months" and "4 months" as fixed text.
        $this->withDemoData();

        $this->get('/teams/TM-1001')
            ->assertSee('Average tenure', false)
            ->assertSee('Active since', false)
            ->assertDontSee('3.2 months', false);
    }

    public function test_the_pages_render_nothing_the_content_security_policy_would_block(): void
    {
        // The handover used onclick on <tr>, onclick="history.back()",
        // style="display:none" and inline <style> blocks — all blocked.
        $this->withDemoData();

        foreach (['/teams', '/teams/mine', '/teams/TM-1001'] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), "inline <style> in {$url}");
            $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), "inline style attribute in {$url}");
            $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), "inline event handler in {$url}");
            $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), "inline <script> in {$url}");
        }
    }

    public function test_the_sidebar_marks_teams_as_current_on_every_team_page(): void
    {
        $this->withDemoData();

        foreach (['/teams', '/teams/mine'] as $url) {
            $this->assertStringContainsString(
                'aria-current="page"',
                $this->get($url)->getContent(),
                "no current marker on {$url}"
            );
        }
    }
}
