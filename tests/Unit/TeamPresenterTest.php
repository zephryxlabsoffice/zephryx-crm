<?php

namespace Tests\Unit;

use App\Support\Chart;
use App\Support\TeamPresenter;
use Tests\TestCase;

class TeamPresenterTest extends TestCase
{
    public function test_it_maps_known_statuses(): void
    {
        $this->assertSame(['tone' => 'pill-green', 'label' => 'Active'], TeamPresenter::status('active'));
        $this->assertSame(['tone' => 'pill-red', 'label' => 'Inactive'], TeamPresenter::status('inactive'));
    }

    public function test_the_chip_drops_the_word_team(): void
    {
        // "Team" is on every one of them and distinguishes nothing, so
        // "Web Development Team" is WD rather than WT.
        $this->assertSame('WD', TeamPresenter::chip('Web Development Team'));
        $this->assertSame('QA', TeamPresenter::chip('Quality Assurance Team'));
        $this->assertSame('HR', TeamPresenter::chip('Human Resources Team'));
    }

    public function test_a_one_word_team_uses_two_letters_of_it(): void
    {
        $this->assertSame('DE', TeamPresenter::chip('Design Team'));
        $this->assertSame('OP', TeamPresenter::chip('Operations'));
    }

    public function test_the_chip_tolerates_untidy_names(): void
    {
        $this->assertSame('??', TeamPresenter::chip('   '));
        $this->assertMatchesRegularExpression('/^.{1,2}$/u', TeamPresenter::chip('Team'));
    }

    public function test_average_tenure_is_computed_not_fixed(): void
    {
        $members = [
            ['joined' => now()->subMonths(6)->toDateString()],
            ['joined' => now()->subMonths(2)->toDateString()],
        ];

        $tenure = TeamPresenter::averageTenure($members);

        $this->assertNotSame('—', $tenure);
        $this->assertStringContainsString('month', $tenure);
    }

    public function test_a_team_with_no_members_has_no_average(): void
    {
        $this->assertSame('—', TeamPresenter::averageTenure([]));
    }

    public function test_the_breakdown_groups_and_sums_to_the_whole(): void
    {
        $rows = [
            ['department' => 'Design'],
            ['department' => 'Design'],
            ['department' => 'Support'],
            ['department' => 'Marketing'],
        ];

        $breakdown = Chart::breakdown($rows, 'department');

        $this->assertSame(4, array_sum(array_column($breakdown, 'count')));
        $this->assertEqualsWithDelta(100.0, array_sum(array_column($breakdown, 'share')), 0.2);
        // Largest first, so the donut reads clockwise by size.
        $this->assertSame('Design', $breakdown[0]['name']);
        $this->assertSame(50.0, $breakdown[0]['share']);
    }

    public function test_an_empty_breakdown_is_empty(): void
    {
        $this->assertSame([], Chart::breakdown([], 'department'));
    }
}
