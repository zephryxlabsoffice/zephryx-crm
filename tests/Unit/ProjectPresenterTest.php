<?php

namespace Tests\Unit;

use App\Support\ProjectPresenter;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProjectPresenterTest extends TestCase
{
    public function test_it_maps_known_statuses_and_priorities(): void
    {
        $this->assertSame(['tone' => 'pill-green', 'label' => 'In Progress'], ProjectPresenter::status('in_progress'));
        $this->assertSame(['tone' => 'pill-blue', 'label' => 'Planning'], ProjectPresenter::status('planning'));
        $this->assertSame(['tone' => 'priority-high', 'label' => 'High'], ProjectPresenter::priority('high'));
    }

    public function test_an_unknown_status_still_renders_readably(): void
    {
        $this->assertSame(
            ['tone' => 'pill-gray', 'label' => 'Awaiting sign off'],
            ProjectPresenter::status('awaiting_sign_off')
        );
    }

    public function test_a_deadline_ahead_reads_as_a_countdown(): void
    {
        $in10 = Carbon::today()->addDays(10)->toDateString();

        $this->assertSame(['label' => 'In 10 days', 'state' => ''], ProjectPresenter::deadline($in10));
    }

    public function test_a_deadline_within_a_week_is_flagged(): void
    {
        $soon = Carbon::today()->addDays(3)->toDateString();

        $this->assertSame('is-soon', ProjectPresenter::deadline($soon)['state']);
        $this->assertSame('Due tomorrow', ProjectPresenter::deadline(Carbon::today()->addDay()->toDateString())['label']);
        $this->assertSame('Due today', ProjectPresenter::deadline(Carbon::today()->toDateString())['label']);
    }

    public function test_a_passed_deadline_reads_as_overdue(): void
    {
        $late = Carbon::today()->subDays(3)->toDateString();

        $this->assertSame(['label' => '3 days overdue', 'state' => 'is-overdue'], ProjectPresenter::deadline($late));
        $this->assertSame('1 day overdue', ProjectPresenter::deadline(Carbon::today()->subDay()->toDateString())['label']);
    }

    public function test_a_delivered_project_is_never_overdue(): void
    {
        // It landed late, which is history, not an outstanding risk. The
        // handover coloured every past date red regardless of status.
        $late = Carbon::today()->subDays(30)->toDateString();

        $this->assertSame(
            ['label' => 'Delivered', 'state' => ''],
            ProjectPresenter::deadline($late, 'completed')
        );
    }

    public function test_barely_started_progress_is_marked(): void
    {
        // A bar that is 5% full reads as an achievement; with the clock
        // running it is closer to a warning.
        $this->assertSame('is-early', ProjectPresenter::progressState(15));
        $this->assertSame('', ProjectPresenter::progressState(20));
        $this->assertSame('', ProjectPresenter::progressState(75));
        // Nothing started at all is not "early", it is simply not begun.
        $this->assertSame('', ProjectPresenter::progressState(0));
    }

    public function test_the_tint_is_stable_for_a_reference(): void
    {
        $first = ProjectPresenter::tint('WD-2024-001');

        $this->assertSame($first, ProjectPresenter::tint('WD-2024-001'));
        $this->assertMatchesRegularExpression('/^tint-[1-7]$/', $first);
    }
}
