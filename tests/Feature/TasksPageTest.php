<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Support\Demo\DemoTasks;
use App\Support\TaskDirectory;
use Tests\TestCase;

/**
 * Tasks — the managing face, the personal face, the team lead's queue and one
 * task. Foundation spec §12.1.
 */
class TasksPageTest extends TestCase
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
     * The demo tasks, as real rows.
     *
     * The environment flip this replaced did nothing once the module read the
     * `tasks` table: there was no row to find, and a test "passing" against an
     * empty page asserts the empty state while claiming to assert the list.
     */
    protected function withDemoData(): void
    {
        $this->seedDemoWorkforce();
    }

    public function test_the_four_pages_render(): void
    {
        $this->withDemoData();

        $this->get('/tasks')->assertOk()->assertSee('Task Overview', false);
        $this->get('/tasks/mine')->assertOk()->assertSee('My Tasks', false);
        $this->get('/tasks/team')->assertOk()->assertSee('Team Tasks', false);
        $this->get('/tasks/TSK-001')->assertOk()->assertSee('Design homepage layout', false);
    }

    public function test_mine_and_team_are_not_read_as_task_references(): void
    {
        $this->get('/tasks/mine')->assertSee('My Tasks', false);
        $this->get('/tasks/team')->assertSee('Team Tasks', false);
    }

    public function test_empty_states_with_no_data(): void
    {
        $this->get('/tasks')->assertSee('No tasks yet.', false);
        $this->get('/tasks/mine')->assertSee('Nothing assigned to you.', false);
        $this->get('/tasks/team')->assertSee('Every team task has someone on it.', false);
    }

    public function test_no_figure_is_written_into_the_markup(): void
    {
        // The handover hardcoded 128 / 18 / 45 / 62 / 12 / 7.
        $response = $this->get('/tasks');

        $response->assertSee('Total Tasks', false);
        $response->assertDontSee('>128<', false);
    }

    public function test_the_percentages_are_computed_from_the_totals(): void
    {
        // The handover's sub-labels were written in and did not add up to the
        // numbers above them.
        $this->withDemoData();

        $stats = TaskDirectory::stats();
        $expected = round($stats['completed'] / $stats['total'] * 100, 1).'% of all';

        $this->get('/tasks')->assertSee($expected, false);
    }

    public function test_the_demo_source_is_inert_outside_local_debug(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);

        $this->assertFalse(DemoTasks::enabled());
        $this->assertTrue(DemoTasks::all()->isEmpty());
        $this->assertSame([], DemoTasks::upcoming());
        $this->assertSame([], DemoTasks::activity());
    }

    public function test_an_unknown_task_is_not_found(): void
    {
        $this->withDemoData();

        $this->get('/tasks/TSK-999')->assertNotFound();
        $this->get('/tasks/'.urlencode('<script>'))->assertNotFound();
    }

    public function test_search_and_filters_work(): void
    {
        $this->withDemoData();

        $this->get('/tasks?q=homepage')->assertSee('/tasks/TSK-001', false);
        $this->get('/tasks?q=homepage')->assertDontSee('/tasks/TSK-003', false);

        $this->get('/tasks?status=completed')->assertSee('/tasks/TSK-005', false);
        $this->get('/tasks?status=completed')->assertDontSee('/tasks/TSK-001', false);

        $this->get('/tasks?priority=low')->assertSee('/tasks/TSK-006', false);
        $this->get('/tasks?priority=low')->assertDontSee('/tasks/TSK-001', false);
    }

    public function test_each_list_filters_itself_rather_than_bouncing_to_the_managing_view(): void
    {
        $this->withDemoData();

        $this->get('/tasks/mine')->assertSee('action="'.route('tasks.mine').'"', false);
        $this->get('/tasks/team')->assertSee('action="'.route('tasks.team').'"', false);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->withDemoData();

        $this->get('/tasks?status=;DROP TABLE')->assertSessionHasErrors('status');
        $this->get('/tasks?priority=urgent')->assertSessionHasErrors('priority');
    }

    public function test_the_team_queue_holds_only_unassigned_tasks(): void
    {
        // The point of the page is deciding who picks each one up.
        $this->withDemoData();

        foreach (Task::unassigned()->whereNotNull('team_id')->get() as $task) {
            $this->assertNull($task->assignee_id, "{$task->reference} already has an assignee");
        }

        $response = $this->get('/tasks/team');
        $response->assertSee('/tasks/TSK-001', false);
        $response->assertDontSee('/tasks/TSK-003', false);
    }

    public function test_a_completed_task_past_its_date_is_not_counted_overdue(): void
    {
        // The same rule Projects uses, so the two do not contradict each other.
        $this->withDemoData();

        $late = Task::query()
            ->where('status', 'completed')
            ->whereDate('due_on', '<', now()->toDateString())
            ->count();

        $this->assertGreaterThan(0, $late, 'the sample data needs a late-but-completed task');
        $this->assertSame(2, TaskDirectory::stats()['overdue']);
    }

    public function test_an_unassigned_team_task_says_so_on_its_page(): void
    {
        $this->withDemoData();

        $this->get('/tasks/TSK-001')
            ->assertSee('Nobody assigned yet', false)
            ->assertSee('Assign someone', false);

        // A task with a person on it does not carry the banner.
        $this->get('/tasks/TSK-003')->assertDontSee('Nobody assigned yet', false);
    }

    public function test_the_timeline_is_what_was_recorded_and_not_a_reconstruction(): void
    {
        /*
         * The demo timeline invented four events from the task's own fields. It
         * looked like history and was a guess: it could say a task had been
         * assigned but never who reassigned it, or when. The audit log answers
         * both, and says nothing when nothing was recorded.
         */
        $this->withDemoData();

        $this->assertSame([], TaskDirectory::timeline('TSK-010'));

        $this->get('/tasks/TSK-010')->assertSee('Nothing has been recorded', false);
    }

    public function test_completing_a_task_is_real_now(): void
    {
        $this->withDemoData();

        $this->get('/tasks/TSK-001')
            ->assertDontSee('Completing a task is not built yet', false)
            ->assertSee('Mark Completed', false);
    }

    public function test_the_description_shown_is_the_tasks_own(): void
    {
        /*
         * The page printed the same paragraph about wireframes and brand
         * guidelines on all fourteen demo tasks. It read as real, which is what
         * made it worth removing — a blank is honest and a filled-in field is
         * the task's.
         */
        $this->withDemoData();

        Task::where('reference', 'TSK-001')->update(['description' => 'Rebuild the hero block.']);

        $this->get('/tasks/TSK-001')
            ->assertSee('Rebuild the hero block.', false)
            ->assertDontSee('brand colours and typography', false);

        $this->get('/tasks/TSK-002')->assertSee('No description was written for this task.', false);
    }

    public function test_the_pages_render_nothing_the_content_security_policy_would_block(): void
    {
        // The handover carried inline styles on the back links and headings,
        // a hidden activity list and inline <style> blocks.
        $this->withDemoData();

        foreach (['/tasks', '/tasks/mine', '/tasks/team', '/tasks/TSK-001'] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), "inline <style> in {$url}");
            $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), "inline style attribute in {$url}");
            $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), "inline event handler in {$url}");
            $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), "inline <script> in {$url}");
        }
    }

    public function test_the_sidebar_marks_tasks_as_current(): void
    {
        $this->withDemoData();

        foreach (['/tasks', '/tasks/mine', '/tasks/team', '/tasks/TSK-001'] as $url) {
            $this->assertSame(
                1,
                substr_count($this->get($url)->getContent(), 'class="sb-link active"'),
                "sidebar current marker wrong on {$url}"
            );
        }
    }
}
