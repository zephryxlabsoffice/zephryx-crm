<?php

namespace Tests\Unit;

use App\Support\DashboardPresenter as P;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardPresenterTest extends TestCase
{
    public function test_the_greeting_follows_the_office_clock(): void
    {
        $this->assertSame('Good morning', P::greeting(Carbon::parse('2026-09-07 06:00')));
        $this->assertSame('Good morning', P::greeting(Carbon::parse('2026-09-07 11:59')));
        $this->assertSame('Good afternoon', P::greeting(Carbon::parse('2026-09-07 12:00')));
        $this->assertSame('Good afternoon', P::greeting(Carbon::parse('2026-09-07 16:59')));
        $this->assertSame('Good evening', P::greeting(Carbon::parse('2026-09-07 17:00')));
        $this->assertSame('Good evening', P::greeting(Carbon::parse('2026-09-07 23:30')));
    }

    public function test_the_greeting_is_the_office_timezone_and_not_utc(): void
    {
        /*
         * 05:00 UTC is 10:30 in the office. Under UTC the dashboard would greet
         * half the working morning as the small hours — the same class of bug
         * that made Attendance move config('app.timezone') off UTC.
         */
        $this->assertSame('Asia/Kolkata', config('app.timezone'));
        $this->assertSame('Good morning', P::greeting(Carbon::parse('2026-09-07 05:00', 'UTC')));
    }

    public function test_it_greets_by_first_name_only(): void
    {
        $this->assertSame('Amit', P::firstName('Amit Verma'));
        $this->assertSame('Amit', P::firstName('  Amit  '));
    }

    public function test_a_missing_name_becomes_a_greeting_and_not_a_blank(): void
    {
        // "Good morning, " is worse than a neutral word, and an id is worse
        // than both.
        $this->assertSame('there', P::firstName(''));
        $this->assertSame('there', P::firstName('   '));
    }

    public function test_a_delta_is_whole_units_in_words_and_never_a_percentage(): void
    {
        $this->assertSame('3 days more than last month', P::delta(8, 5, 'day')['label']);
        $this->assertSame('2 days fewer than last month', P::delta(3, 5, 'day')['label']);
        $this->assertSame('Same as last month', P::delta(5, 5, 'day')['label']);

        // The handover's shape, and the reason it is gone: a percentage on a
        // count of two reads as a crisis and means one.
        $this->assertStringNotContainsString('%', P::delta(2, 1, 'day')['label']);
    }

    public function test_a_delta_of_one_is_singular(): void
    {
        $this->assertSame('1 day more than last month', P::delta(6, 5, 'day')['label']);
    }

    public function test_zero_is_a_word_rather_than_a_digit(): void
    {
        // "No tasks overdue" reads as good news; "0 tasks overdue" reads as a
        // widget that failed to load.
        $this->assertSame('No tasks', P::count(0, 'task'));
        $this->assertSame('1 task', P::count(1, 'task'));
        $this->assertSame('4 tasks', P::count(4, 'task'));
    }

    public function test_an_irregular_plural_can_be_given(): void
    {
        $this->assertSame('2 people', P::count(2, 'person', 'people'));
    }
}
