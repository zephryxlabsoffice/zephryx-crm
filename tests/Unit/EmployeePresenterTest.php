<?php

namespace Tests\Unit;

use App\Support\EmployeePresenter;
use PHPUnit\Framework\TestCase;

class EmployeePresenterTest extends TestCase
{
    public function test_it_maps_known_statuses(): void
    {
        $this->assertSame(['tone' => 'pill-green', 'label' => 'Active'], EmployeePresenter::status('active'));
        $this->assertSame(['tone' => 'pill-amber', 'label' => 'On Leave'], EmployeePresenter::status('on_leave'));
    }

    public function test_an_unknown_status_still_renders_readably(): void
    {
        $this->assertSame(
            ['tone' => 'pill-gray', 'label' => 'Notice period'],
            EmployeePresenter::status('notice_period')
        );
    }

    public function test_the_donut_segments_tile_the_full_circle(): void
    {
        $circumference = 2 * M_PI * 57;
        $shares = [40.0, 35.0, 25.0];

        $drawn = 0.0;
        $offset = 0.0;

        foreach ($shares as $share) {
            $segment = EmployeePresenter::donutSegment($share, $offset, $circumference);

            // dasharray is "<drawn> <gap>"; the two must add up to the circle.
            [$dash, $gap] = array_map('floatval', explode(' ', $segment['dash']));
            $this->assertEqualsWithDelta($circumference, $dash + $gap, 0.05);

            // Each segment starts exactly where the previous one ended.
            $this->assertEqualsWithDelta(
                -$circumference * ($offset / 100),
                (float) $segment['offset'],
                0.05
            );

            $drawn += $dash;
            $offset += $share;
        }

        $this->assertEqualsWithDelta($circumference, $drawn, 0.05);
    }

    public function test_a_single_department_fills_the_ring(): void
    {
        $circumference = 2 * M_PI * 57;

        [$dash, $gap] = array_map('floatval', explode(' ', EmployeePresenter::donutSegment(100, 0, $circumference)['dash']));

        $this->assertEqualsWithDelta($circumference, $dash, 0.05);
        $this->assertEqualsWithDelta(0.0, $gap, 0.05);
    }

    public function test_the_palette_cycles_so_master_data_of_any_size_gets_a_colour(): void
    {
        $this->assertSame('dot-1', EmployeePresenter::dot(0));
        $this->assertSame('dot-8', EmployeePresenter::dot(7));
        // Departments are master data — the ninth must not be colourless.
        $this->assertSame('dot-1', EmployeePresenter::dot(8));
        $this->assertSame('dot-4', EmployeePresenter::dot(19));
    }

    public function test_join_dates_render_consistently(): void
    {
        $this->assertSame('12 Jan 2024', EmployeePresenter::joined('2024-01-12'));
    }
}
