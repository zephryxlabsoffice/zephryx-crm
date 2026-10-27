<?php

namespace Tests\Unit;

use App\Support\SalaryPresenter;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * The payslip month picker (fixed 2026-10-27): it used to stop at twelve
 * months, hiding every older payslip.
 */
class SalaryPeriodOptionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-27'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_with_no_history_it_offers_the_last_twelve_months(): void
    {
        $options = SalaryPresenter::periodOptions();

        $this->assertCount(12, $options);
        $this->assertSame('2026-10', array_key_first($options));
        $this->assertSame('2025-11', array_key_last($options));
    }

    public function test_it_reaches_back_to_the_earliest_month(): void
    {
        $options = SalaryPresenter::periodOptions('2023-04');

        $this->assertSame('2026-10', array_key_first($options));
        $this->assertSame('2023-04', array_key_last($options));
        $this->assertCount(43, $options);
    }

    public function test_a_recent_earliest_month_still_leaves_twelve(): void
    {
        $this->assertCount(12, SalaryPresenter::periodOptions('2026-08'));
    }

    public function test_the_month_being_viewed_is_always_offered(): void
    {
        $options = SalaryPresenter::periodOptions(null, '2026-12');

        $this->assertSame('December 2026', $options['2026-12']);
        $this->assertSame('2026-12', array_key_first($options));
    }
}
