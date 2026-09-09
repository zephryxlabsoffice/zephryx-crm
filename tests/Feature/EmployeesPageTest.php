<?php

namespace Tests\Feature;

use App\Support\EmployeeDirectory;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Employees — GET /employees.
 *
 * Rows come from the `employees` table. Tests that need people call
 * seedDemoWorkforce(); the ones that do not are asserting the empty state on
 * purpose, which is what a freshly deployed site shows.
 */
class EmployeesPageTest extends TestCase
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
     * Undo any frozen clock, or every test after it in the process runs in
     * March.
     */
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
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

    public function test_a_deployed_site_with_no_employees_shows_no_invented_ones(): void
    {
        /*
         * This replaced a test that the demo SOURCE was inert outside local +
         * debug. That guard mattered while the page read from a fixture; now it
         * reads from a table, and the guarantee worth having is the stronger
         * one — an empty database renders empty, with no fallback content from
         * anywhere.
         */
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);

        $this->assertSame(0, EmployeeDirectory::all()->count());
        $this->assertSame([], EmployeeDirectory::byDepartment());
        $this->assertSame([], EmployeeDirectory::recentStarters());
        $this->assertSame(0, EmployeeDirectory::stats()['total']);
    }

    public function test_it_lists_employees_and_paginates_them(): void
    {
        $this->seedDemoWorkforce();

        $first = $this->get('/employees');
        $first->assertSee('Riya Sharma', false);
        $first->assertSee('Showing 1 to 8 of 12 employees', false);

        $second = $this->get('/employees?page=2');
        $second->assertSee('Showing 9 to 12 of 12 employees', false);
    }

    public function test_search_covers_name_staff_id_and_role(): void
    {
        $this->seedDemoWorkforce();

        $this->get('/employees?q=riya')->assertSee('/employees/EMP001', false);
        $this->get('/employees?q=EMP004')->assertSee('/employees/EMP004', false);
        $this->get('/employees?q=accountant')->assertSee('/employees/EMP006', false);
        $this->get('/employees?q=riya')->assertDontSee('/employees/EMP002', false);
    }

    public function test_department_and_status_filters_work(): void
    {
        $this->seedDemoWorkforce();

        $dev = $this->get('/employees?department=Development');
        $dev->assertSee('/employees/EMP002', false);
        $dev->assertDontSee('/employees/EMP001', false);

        /*
         * Status filters on the ACCOUNT's status now. `on_leave` was in the demo
         * rows as a stored value and is not one: it is a question about today
         * that an approved leave request answers, and it comes back as a filter
         * when the Leave module has a table to ask.
         */
        $inactive = $this->get('/employees?status=inactive');
        $inactive->assertSee('/employees/EMP012', false);
        $inactive->assertDontSee('/employees/EMP001', false);
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $this->seedDemoWorkforce();

        $this->get('/employees?status=;DROP TABLE')->assertSessionHasErrors('status');
    }

    public function test_the_department_breakdown_sums_to_the_headcount(): void
    {
        $this->seedDemoWorkforce();

        $breakdown = EmployeeDirectory::byDepartment();
        $total = EmployeeDirectory::all()->count();

        $this->assertSame($total, array_sum(array_column($breakdown, 'count')));
        // Largest department first, so the donut reads clockwise by size.
        $counts = array_column($breakdown, 'count');
        $this->assertSame($counts, collect($counts)->sortDesc()->values()->all());
    }

    public function test_the_chart_is_not_the_only_source_of_its_own_numbers(): void
    {
        // The donut is aria-hidden; the legend beside it is a real list, so the
        // figures are reachable without seeing or distinguishing the colours.
        $this->seedDemoWorkforce();

        $html = $this->get('/employees')->getContent();

        $this->assertStringContainsString('class="chart-legend"', $html);
        $this->assertStringContainsString('<svg viewBox="0 0 132 132" aria-hidden="true">', $html);
        $this->assertStringContainsString('Development', $html);
    }

    public function test_birthdays_show_a_day_and_a_month_and_never_a_year(): void
    {
        // This card was empty until Announcements landed (2026-08-28) and date
        // of birth arrived with it. It is stored in full; the year never
        // reaches a page — a colleague needs to know when to say happy
        // birthday, not how old somebody is.
        //
        // The clock is frozen on a day one of the demo birthdays is near,
        // because the window is fourteen days and they are spread across the
        // year — otherwise this test asserted the rail had content on the
        // strength of today's date. See AnnouncementsPageTest for the long
        // version of the argument.
        Carbon::setTestNow(Carbon::parse('2026-03-05'));

        $this->seedDemoWorkforce();

        $birthdays = EmployeeDirectory::birthdays();

        $this->assertNotEmpty($birthdays, 'no birthday in the window to review');

        $html = $this->get('/employees')->getContent();

        foreach ($birthdays as $birthday) {
            $this->assertMatchesRegularExpression('/^\d{2} [A-Z][a-z]{2}$/', $birthday['date']);
            $this->assertStringContainsString($birthday['name'], $html);
        }

        foreach (EmployeeDirectory::all()->pluck('dob')->filter() as $dob) {
            $this->assertStringNotContainsString($dob, $html);
            $this->assertStringNotContainsString(\Illuminate\Support\Carbon::parse($dob)->format('d M Y'), $html);
        }
    }

    public function test_somebody_who_opted_out_has_no_birthday_shown(): void
    {
        $this->seedDemoWorkforce();

        $optedOut = EmployeeDirectory::all()
            ->first(fn (array $e) => ($e['announce_milestones'] ?? true) === false);

        $this->assertNotNull($optedOut);

        foreach (EmployeeDirectory::birthdays(50) as $birthday) {
            $this->assertNotSame($optedOut['name'], $birthday['name']);
        }
    }

    public function test_the_page_renders_nothing_the_content_security_policy_would_block(): void
    {
        // The handover set the legend colours with style="--dot:#15A848",
        // which our CSP blocks outright.
        $this->seedDemoWorkforce();

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
