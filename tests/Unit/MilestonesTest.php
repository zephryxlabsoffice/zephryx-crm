<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\Team;
use App\Support\Milestones;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MilestonesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Milestones reads employees from the database now, so the people it
        // computes birthdays and anniversaries for have to actually be there.
        // The environment flip alone stopped conjuring them.
        $this->seedDemoWorkforce();
    }

    /* ══════════════════════════════════════════════════════════════════════
       A BIRTHDAY IS A DAY AND A MONTH

       Date of birth is stored in full, but the year must never reach a page.
       A colleague needs to know when to say happy birthday, not how old
       somebody is.
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_birthday_is_formatted_without_its_year(): void
    {
        $this->assertSame('14 Mar', Milestones::dayAndMonth('1996-03-14'));
        $this->assertStringNotContainsString('1996', Milestones::dayAndMonth('1996-03-14'));
    }

    public function test_no_birth_year_appears_anywhere_in_a_milestone(): void
    {
        // Walks every field of every milestone, so a year cannot creep in
        // through a title or a body somebody adds later.
        foreach (Milestones::upcoming(null, 400) as $milestone) {
            if ($milestone['kind'] !== Milestones::BIRTHDAY) {
                continue;
            }

            $year = Carbon::parse($milestone['employee']['dob'])->format('Y');
            $text = json_encode(array_diff_key($milestone, ['employee' => null]));

            $this->assertStringNotContainsString($year, $text, "a birth year leaked from {$milestone['employee']['name']}'s milestone");
        }
    }

    public function test_a_birthday_carries_no_year_count(): void
    {
        // Anniversaries have one; birthdays deliberately do not.
        foreach (Milestones::upcoming(Milestones::BIRTHDAY, 400) as $milestone) {
            $this->assertNull($milestone['years']);
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       ANNIVERSARIES
       ══════════════════════════════════════════════════════════════════════ */

    public function test_an_anniversary_states_how_many_years(): void
    {
        $found = Milestones::upcoming(Milestones::ANNIVERSARY, 400);

        $this->assertNotEmpty($found, 'no anniversary in the sample data');

        foreach ($found as $milestone) {
            $this->assertGreaterThanOrEqual(1, $milestone['years']);
            $this->assertStringContainsString('year', $milestone['body'].$milestone['title']);
        }
    }

    public function test_nobody_gets_a_nought_year_anniversary_on_their_first_day(): void
    {
        foreach (Milestones::upcoming(Milestones::ANNIVERSARY, 400) as $milestone) {
            $this->assertNotSame(0, $milestone['years']);
        }
    }

    public function test_years_reads_in_the_singular(): void
    {
        $this->assertSame('1 year', Milestones::years(1));
        $this->assertSame('2 years', Milestones::years(2));
    }

    /* ══════════════════════════════════════════════════════════════════════
       WHO IS INCLUDED
       ══════════════════════════════════════════════════════════════════════ */

    public function test_somebody_who_opted_out_is_never_celebrated(): void
    {
        // Opting out removes them entirely — it does not post a quieter version.
        // Read from the directory, not the fixture: the fixture is inert once
        // seedDemoWorkforce() has put the environment back.
        $optedOut = \App\Support\EmployeeDirectory::all()
            ->first(fn (array $e) => $e['announce_milestones'] === false);

        $this->assertNotNull($optedOut, 'no opted-out employee in the sample data');

        foreach (Milestones::upcoming(null, 400) as $milestone) {
            $this->assertNotSame(
                $optedOut['user_id'],
                $milestone['employee']['user_id'],
                'an opted-out person appeared in the milestone feed'
            );
        }
    }

    public function test_somebody_who_has_left_is_not_celebrated(): void
    {
        foreach (Milestones::upcoming(null, 400) as $milestone) {
            $this->assertNotSame('inactive', $milestone['employee']['status']);
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       DATES
       ══════════════════════════════════════════════════════════════════════ */

    public function test_only_milestones_inside_the_window_are_returned(): void
    {
        // Asserted against a wide window rather than by looping a narrow one:
        // on a quiet week a seven-day window is empty, and a loop over nothing
        // is a test that passes without checking anything.
        $wide = Milestones::upcoming(null, 400);
        $narrow = Milestones::upcoming(null, 7);

        $this->assertNotEmpty($wide, 'no milestones at all in the sample data');
        $this->assertLessThanOrEqual(count($wide), count($narrow));

        foreach ($narrow as $milestone) {
            $this->assertGreaterThanOrEqual(0, $milestone['in_days']);
            $this->assertLessThanOrEqual(7, $milestone['in_days']);
        }

        // And nothing beyond the wide window sneaks in either.
        foreach ($wide as $milestone) {
            $this->assertLessThanOrEqual(400, $milestone['in_days']);
        }
    }

    public function test_they_come_back_soonest_first(): void
    {
        $days = array_column(Milestones::upcoming(null, 400), 'in_days');
        $sorted = $days;
        sort($sorted);

        $this->assertSame($sorted, $days);
    }

    public function test_today_returns_exactly_what_falls_today(): void
    {
        // Asserted as a relationship, not by looping: on most days nothing
        // falls today, and a loop over nothing passes without checking
        // anything.
        $today = Milestones::today();
        $expected = array_values(array_filter(
            Milestones::upcoming(null, 400),
            fn (array $m) => $m['in_days'] === 0
        ));

        $this->assertSame(count($expected), count($today));
        $this->assertSame(array_column($expected, 'date'), array_column($today, 'date'));

        foreach ($today as $milestone) {
            $this->assertSame(0, $milestone['in_days']);
            $this->assertSame(Carbon::today()->toDateString(), $milestone['date']);
        }
    }

    public function test_a_29_february_birthday_is_kept_in_february(): void
    {
        // Carbon would roll 29 Feb to 1 March in a common year, which quietly
        // moves somebody's birthday into the wrong month.
        $method = new \ReflectionMethod(Milestones::class, 'nextOccurrence');
        $method->setAccessible(true);

        // 2027 is not a leap year.
        $next = $method->invoke(null, '2000-02-29', Carbon::create(2027, 1, 15));

        $this->assertSame('02', $next->format('m'));
        $this->assertSame('28', $next->format('d'));
    }

    public function test_a_date_already_past_this_year_rolls_to_next_year(): void
    {
        $method = new \ReflectionMethod(Milestones::class, 'nextOccurrence');
        $method->setAccessible(true);

        $next = $method->invoke(null, '1990-01-17', Carbon::create(2026, 8, 28));

        $this->assertSame('2027-01-17', $next->toDateString());
    }

    public function test_a_date_falling_today_counts_as_today_not_next_year(): void
    {
        $method = new \ReflectionMethod(Milestones::class, 'nextOccurrence');
        $method->setAccessible(true);

        $next = $method->invoke(null, '1990-08-28', Carbon::create(2026, 8, 28));

        $this->assertSame('2026-08-28', $next->toDateString());
    }

    public function test_labels_read_in_plain_words(): void
    {
        $this->assertSame('Today', Milestones::label(0));
        $this->assertSame('Tomorrow', Milestones::label(1));
        $this->assertSame('In 5 days', Milestones::label(5));
    }

    public function test_nothing_is_returned_when_there_are_no_employees(): void
    {
        /*
         * This replaced "nothing is returned outside local + debug", which was
         * true only because milestones came from a fixture that switched itself
         * off. They come from the `employees` table now, and in production a
         * real birthday SHOULD be announced — asserting otherwise would have
         * been asserting the feature does not work where it matters.
         *
         * What survives is the guarantee underneath it: nobody is invented. No
         * employees, no milestones, in any environment.
         */
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);

        /*
         * Teams first. `team_members.employee_id` is restricted on delete —
         * somebody's team history is part of their record — so emptying the
         * employees table means emptying what points at it. Dropping the teams
         * cascades their memberships away.
         */
        Team::query()->delete();
        Employee::query()->delete();

        $this->assertSame([], Milestones::upcoming());
        $this->assertSame([], Milestones::today());
    }
}
