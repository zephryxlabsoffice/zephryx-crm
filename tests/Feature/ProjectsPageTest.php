<?php

namespace Tests\Feature;

use App\Support\Demo\DemoProjects;
use Tests\TestCase;

/**
 * Projects — the managing face, the personal face, the EOD list and one
 * project's overview. Foundation spec §12.1.
 */
class ProjectsPageTest extends TestCase
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

        $this->get('/projects')->assertOk()->assertSee('Project Management', false);
        $this->get('/projects/mine')->assertOk()->assertSee('My Projects', false);
        $this->get('/projects/updates')->assertOk()->assertSee('End of Day Updates', false);
        $this->get('/projects/WD-2024-001')->assertOk()->assertSee('Website Redesign', false);
    }

    public function test_mine_and_updates_are_not_read_as_project_references(): void
    {
        // Route order: /projects/{project} would otherwise swallow both.
        $this->get('/projects/mine')->assertSee('My Projects', false);
        $this->get('/projects/updates')->assertSee('End of Day Updates', false);
    }

    public function test_empty_states_with_no_data(): void
    {
        $this->get('/projects')->assertSee('No projects yet.', false);
        $this->get('/projects/mine')->assertSee('No projects assigned to you.', false);
        $this->get('/projects/updates')->assertSee('There is nothing to report on today.', false);
    }

    public function test_no_figure_is_written_into_the_markup(): void
    {
        // The handover hardcoded 32 / 18 / 12 / 2 / 4.
        $response = $this->get('/projects');

        $response->assertSee('Total Projects', false);
        $response->assertDontSee('>32<', false);
    }

    public function test_the_demo_source_is_inert_outside_local_debug(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);

        $this->assertFalse(DemoProjects::enabled());
        $this->assertTrue(DemoProjects::all()->isEmpty());
        $this->assertSame([], DemoProjects::upcoming());
    }

    public function test_an_unknown_project_is_not_found(): void
    {
        $this->withDemoData();

        $this->get('/projects/ZZ-9999')->assertNotFound();
        $this->get('/projects/'.urlencode('<script>'))->assertNotFound();
    }

    public function test_search_and_filters_work(): void
    {
        $this->withDemoData();

        // Asserted against the page body, not the whole document: the topbar
        // notification bell links to whatever the viewer has been notified
        // about, so "Website Redesign is due in 5 days" puts
        // /projects/WD-2024-001 into the HTML of every page in the application.
        $search = $this->pageBody('/projects?q=redesign');
        $this->assertStringContainsString('/projects/WD-2024-001', $search);
        $this->assertStringNotContainsString('/projects/CRM-2024-003', $search);

        $completed = $this->pageBody('/projects?status=completed');
        $this->assertStringContainsString('/projects/CW-2024-007', $completed);
        $this->assertStringNotContainsString('/projects/WD-2024-001', $completed);

        $low = $this->pageBody('/projects?priority=low');
        $this->assertStringContainsString('/projects/APP-2024-006', $low);
        $this->assertStringNotContainsString('/projects/WD-2024-001', $low);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->withDemoData();

        $this->get('/projects?status=;DROP TABLE')->assertSessionHasErrors('status');
        $this->get('/projects?priority=urgent')->assertSessionHasErrors('priority');
    }

    public function test_progress_uses_a_native_element_not_an_inline_width(): void
    {
        // The handover sized the bar with style="width:75%", which our
        // Content-Security-Policy blocks — every bar would have been empty.
        $this->withDemoData();

        $html = $this->get('/projects')->getContent();

        $this->assertStringContainsString('<progress class="progress" max="100" value="75"', $html);
        $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), 'inline style attribute');
    }

    public function test_a_completed_project_past_its_date_is_not_counted_overdue(): void
    {
        // It landed late, which is history, not an outstanding problem.
        $this->withDemoData();

        $stats = DemoProjects::stats();
        $completedAndLate = DemoProjects::all()
            ->filter(fn (array $p) => $p['status'] === 'completed' && $p['due_in'] < 0)
            ->count();

        $this->assertGreaterThan(0, $completedAndLate, 'the sample data needs a late-but-delivered project');
        $this->assertSame(2, $stats['overdue']);
    }

    public function test_upcoming_deadlines_exclude_finished_work_and_are_soonest_first(): void
    {
        $this->withDemoData();

        $upcoming = DemoProjects::upcoming();

        $this->assertNotEmpty($upcoming);
        foreach ($upcoming as $project) {
            $this->assertNotSame('completed', $project['status']);
        }

        $order = array_column($upcoming, 'due_in');
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order);
    }

    public function test_the_status_control_does_not_pretend_to_work(): void
    {
        $this->withDemoData();

        $this->get('/projects/WD-2024-001')
            ->assertSee('Changing status is not built yet', false);
    }

    public function test_the_overview_lists_assigned_teams(): void
    {
        $this->withDemoData();

        $response = $this->get('/projects/WD-2024-001');

        $response->assertSee('Assigned Teams', false);
        $response->assertSee('/teams/TM-1001', false);
    }

    public function test_the_pages_render_nothing_the_content_security_policy_would_block(): void
    {
        $this->withDemoData();

        foreach (['/projects', '/projects/mine', '/projects/updates', '/projects/WD-2024-001'] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), "inline <style> in {$url}");
            $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), "inline style attribute in {$url}");
            $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), "inline event handler in {$url}");
            $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), "inline <script> in {$url}");
        }
    }

    public function test_the_sidebar_marks_projects_as_current(): void
    {
        $this->withDemoData();

        // Scoped to the sidebar: pagination also uses aria-current="page" for
        // the page you are on, which is correct and not what this is checking.
        foreach (['/projects', '/projects/mine', '/projects/updates', '/projects/WD-2024-001'] as $url) {
            $this->assertSame(
                1,
                substr_count($this->get($url)->getContent(), 'class="sb-link active"'),
                "sidebar current marker wrong on {$url}"
            );
        }
    }
}
