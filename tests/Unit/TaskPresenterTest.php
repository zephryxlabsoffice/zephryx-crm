<?php

namespace Tests\Unit;

use App\Support\ProjectPresenter;
use App\Support\TaskPresenter;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskPresenterTest extends TestCase
{
    public function test_it_maps_known_statuses(): void
    {
        $this->assertSame(['tone' => 'pill-green', 'label' => 'In Progress'], TaskPresenter::status('in_progress'));
        $this->assertSame(['tone' => 'pill-gray', 'label' => 'Pending'], TaskPresenter::status('pending'));
    }

    public function test_an_unknown_status_still_renders_readably(): void
    {
        $this->assertSame(
            ['tone' => 'pill-gray', 'label' => 'Needs info'],
            TaskPresenter::status('needs_info')
        );
    }

    public function test_priority_matches_projects_exactly(): void
    {
        // The same word must mean the same thing in both modules, or a "High"
        // task and a "High" project would look like different scales.
        foreach (['high', 'medium', 'low'] as $level) {
            $this->assertSame(ProjectPresenter::priority($level), TaskPresenter::priority($level));
        }
    }

    public function test_a_due_date_ahead_reads_as_a_countdown(): void
    {
        $this->assertSame(
            ['label' => '10 days left', 'state' => ''],
            TaskPresenter::due(Carbon::today()->addDays(10)->toDateString())
        );
        $this->assertSame('is-soon', TaskPresenter::due(Carbon::today()->addDays(3)->toDateString())['state']);
        $this->assertSame('1 day left', TaskPresenter::due(Carbon::today()->addDay()->toDateString())['label']);
        $this->assertSame('Due today', TaskPresenter::due(Carbon::today()->toDateString())['label']);
    }

    public function test_a_passed_due_date_reads_as_overdue(): void
    {
        $this->assertSame(
            ['label' => '3 days overdue', 'state' => 'is-overdue'],
            TaskPresenter::due(Carbon::today()->subDays(3)->toDateString())
        );
        $this->assertSame('1 day overdue', TaskPresenter::due(Carbon::today()->subDay()->toDateString())['label']);
    }

    public function test_a_completed_task_is_never_overdue(): void
    {
        $this->assertSame(
            ['label' => 'Completed', 'state' => 'is-done'],
            TaskPresenter::due(Carbon::today()->subDays(30)->toDateString(), 'completed')
        );
    }

    public function test_file_types_map_to_a_badge(): void
    {
        $this->assertSame(['class' => 'ft-pdf', 'label' => 'PDF'], TaskPresenter::fileType('Brand_Guidelines.pdf'));
        $this->assertSame(['class' => 'ft-design', 'label' => 'FIG'], TaskPresenter::fileType('Wireframe.fig'));
        $this->assertSame(['class' => 'ft-img', 'label' => 'IMG'], TaskPresenter::fileType('logo.PNG'));
        $this->assertSame(['class' => 'ft-sheet', 'label' => 'XLS'], TaskPresenter::fileType('budget.csv'));
    }

    public function test_an_unknown_file_type_still_gets_a_badge(): void
    {
        $this->assertSame(['class' => '', 'label' => 'ZIP'], TaskPresenter::fileType('archive.zip'));
        $this->assertSame(['class' => '', 'label' => 'FILE'], TaskPresenter::fileType('README'));
    }
}
