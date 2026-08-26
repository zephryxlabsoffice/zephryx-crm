<?php

namespace Tests\Feature;

use App\Support\Demo\DemoEmployees;
use Tests\TestCase;

/**
 * Employees — GET /employees.
 *
 * There is no `users` table yet. Rows come from DemoEmployees, which is inert
 * outside local + debug, so most of these run against the empty state.
 */
class EmployeesPageTest extends TestCase
{
    protected function withDemoData(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);
    }

    public function test_it_renders_inside_the_app_shell(): void
    {
        $response = $this->get('/employees');

        $response->assertOk();
        $response->assertSee('Employees Management', false);
        $response->assertSee('class="sidebar"', false);
    }

    public function test_the_sidebar_marks_employees_as_current(): void
    {
        $response = $this->get('/employees');

        $this->assertSame(1, substr_count($response->getContent(), 'aria-current="page"'));
    }

    public function test_it_shows_an_empty_state_with_no_employees(): void
    {
        $response = $this->get('/employees');

        $response->assertSee('No employees yet.', false);
        $response->assertSee('No departments to show yet.', false);
    }

    public function test_no_figure_is_written_into_the_markup(): void
    {
        // The handover hardcoded 58 / 48 / 4 / 6 and "20% vs last month".
        $response = $this->get('/employees');

        $response->assertSee('Total Employees', false);
        $response->assertDontSee('vs last month', false);
        $response->assertDontSee('>58<', false);
    }

    public function test_the_demo_source_is_inert_outside_local_debug(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);

        $this->assertFalse(DemoEmployees::enabled());
        $this->assertTrue(DemoEmployees::all()->isEmpty());
        $this->assertSame([], DemoEmployees::byDepartment());
        $this->assertSame([], DemoEmployees::recentStarters());
    }

    public function test_it_lists_employees_and_paginates_them(): void
    {
        $this->withDemoData();

        $first = $this->get('/employees');
        $first->assertSee('Riya Sharma', false);
        $first->assertSee('Showing 1 to 8 of 12 employees', false);

        $second = $this->get('/employees?page=2');
        $second->assertSee('Showing 9 to 12 of 12 employees', false);
    }

    public function test_search_covers_name_staff_id_and_role(): void
    {
        $this->withDemoData();

        $this->get('/employees?q=riya')->assertSee('/employees/EMP001', false);
        $this->get('/employees?q=EMP004')->assertSee('/employees/EMP004', false);
        $this->get('/employees?q=accountant')->assertSee('/employees/EMP006', false);
        $this->get('/employees?q=riya')->assertDontSee('/employees/EMP002', false);
    }

    public function test_department_and_status_filters_work(): void
    {
        $this->withDemoData();

        $dev = $this->get('/employees?department=Development');
        $dev->assertSee('/employees/EMP002', false);
        $dev->assertDontSee('/employees/EMP001', false);

        $leave = $this->get('/employees?status=on_leave');
        $leave->assertSee('/employees/EMP005', false);
        $leave->assertDontSee('/employees/EMP001', false);
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $this->withDemoData();

        $this->get('/employees?status=;DROP TABLE')->assertSessionHasErrors('status');
    }

    public function test_the_department_breakdown_sums_to_the_headcount(): void
    {
        $this->withDemoData();

        $breakdown = DemoEmployees::byDepartment();
        $total = DemoEmployees::all()->count();

        $this->assertSame($total, array_sum(array_column($breakdown, 'count')));
        // Largest department first, so the donut reads clockwise by size.
        $counts = array_column($breakdown, 'count');
        $this->assertSame($counts, collect($counts)->sortDesc()->values()->all());
    }

    public function test_the_chart_is_not_the_only_source_of_its_own_numbers(): void
    {
        // The donut is aria-hidden; the legend beside it is a real list, so the
        // figures are reachable without seeing or distinguishing the colours.
        $this->withDemoData();

        $html = $this->get('/employees')->getContent();

        $this->assertStringContainsString('class="dept-legend"', $html);
        $this->assertStringContainsString('<svg viewBox="0 0 132 132" aria-hidden="true">', $html);
        $this->assertStringContainsString('Development', $html);
    }

    public function test_birthdays_are_not_invented(): void
    {
        // Date of birth is a field the Employees module does not have yet. A
        // wrong birthday is worse than an absent one.
        $this->withDemoData();

        $this->get('/employees')->assertSee('No birthdays recorded yet.', false);
    }

    public function test_the_page_renders_nothing_the_content_security_policy_would_block(): void
    {
        // The handover set the legend colours with style="--dot:#15A848",
        // which our CSP blocks outright.
        $this->withDemoData();

        $html = $this->get('/employees')->getContent();

        $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), 'inline <style> block');
        $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), 'inline style attribute');
        $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), 'inline event handler');
        $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), 'inline <script> block');
    }

    public function test_import_and_export_are_not_pretending_to_work(): void
    {
        $response = $this->get('/employees');

        $response->assertSee('Import is not built yet', false);
        $response->assertSee('Export is not built yet', false);
    }
}
