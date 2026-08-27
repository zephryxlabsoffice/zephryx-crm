<?php

namespace Tests\Feature;

use App\Support\Demo\DemoEmployees;
use App\Support\Demo\DemoSalaries;
use App\Support\Money;
use App\Support\SalaryPresenter;
use Tests\TestCase;

class SalaryPageTest extends TestCase
{
    protected function withDemoData(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        config(['app.debug' => true]);
    }

    protected function period(): string
    {
        return now()->format('Y-m');
    }

    public function test_the_pages_render(): void
    {
        $this->withDemoData();

        $this->get('/salary')->assertOk()->assertSee('Salary Management', false);
        $this->get('/salary/mine')->assertOk()->assertSee('My Salary', false);
        $this->get('/salary/payslip/'.$this->period())->assertOk()->assertSee('Payslip', false);
        $this->get('/salary/EMP004/'.$this->period())->assertOk();
    }

    public function test_mine_and_payslip_are_not_read_as_employee_references(): void
    {
        $this->withDemoData();

        $this->get('/salary/mine')->assertOk();
        $this->get('/salary/payslip/'.$this->period())->assertOk();
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE TESTS THIS MODULE EXISTS FOR

       A leak here is somebody's bank account or government ID. These assert
       against the rendered HTML, not against a helper, because the question is
       what reached the browser.
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * Every unmasked identifier in the sample data, so a leak anywhere is
     * caught rather than only the ones somebody thought to check.
     *
     * @return list<string>
     */
    protected function everySecret(): array
    {
        $secrets = [];

        foreach (DemoSalaries::all() as $run) {
            $structure = $run['structure'];
            $secrets[] = $structure['account'];
            $secrets[] = $structure['pan'];
            $secrets[] = $structure['aadhaar'];
        }

        return array_values(array_unique($secrets));
    }

    public function test_the_payroll_list_contains_no_bank_pan_or_aadhaar_at_all(): void
    {
        // Not masked — absent. A masked value on a list of everybody still
        // confirms an account exists and hands over its last four digits for
        // twelve people at once.
        $this->withDemoData();

        $html = $this->get('/salary')->getContent();

        foreach ($this->everySecret() as $secret) {
            $this->assertStringNotContainsString($secret, $html, 'an identifier reached the payroll list');
        }

        // And not even the masked forms.
        $this->assertStringNotContainsString('••••', $html);
        $this->assertStringNotContainsString('XXXX XXXX', $html);
    }

    public function test_another_persons_payslip_carries_none_of_their_identifiers(): void
    {
        $this->withDemoData();

        $html = $this->get('/salary/EMP004/'.$this->period())->getContent();

        foreach ($this->everySecret() as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }

        $this->assertStringNotContainsString('••••', $html);
        // And it says so, rather than looking broken.
        $this->assertStringContainsString('are not shown here', $html);
    }

    public function test_your_own_page_shows_masked_values_and_never_the_full_ones(): void
    {
        $this->withDemoData();

        $viewer = DemoSalaries::VIEWER;
        $run = DemoSalaries::latestFor($viewer);
        $structure = $run['structure'];

        foreach (['/salary/mine', '/salary/payslip/'.$this->period()] as $url) {
            $html = $this->get($url)->getContent();

            // The full values are not there.
            $this->assertStringNotContainsString($structure['account'], $html, "full account number in {$url}");
            $this->assertStringNotContainsString($structure['pan'], $html, "full PAN in {$url}");
            $this->assertStringNotContainsString($structure['aadhaar'], $html, "full Aadhaar in {$url}");

            // The masked ones are.
            $this->assertStringContainsString('•••• •••• '.substr($structure['account'], -4), $html);
            $this->assertStringContainsString('XXXX XXXX '.substr($structure['aadhaar'], -4), $html);
        }
    }

    public function test_no_identifier_is_hidden_in_an_attribute_rather_than_omitted(): void
    {
        // The specific failure this guards: masking in the browser instead of
        // in PHP. Anything sent to the browser has already been read by whoever
        // is at the browser — a `data-` attribute or a title is not a control.
        $this->withDemoData();

        foreach (['/salary', '/salary/mine', '/salary/EMP004/'.$this->period(), '/salary/payslip/'.$this->period()] as $url) {
            $html = $this->get($url)->getContent();

            foreach ($this->everySecret() as $secret) {
                $this->assertStringNotContainsString($secret, $html, "{$secret} appears anywhere in {$url}");
            }
        }
    }

    public function test_the_payslip_route_takes_no_employee_to_tamper_with(): void
    {
        // The common path is safe by construction: the person comes from the
        // session, so there is no ownership check anybody can forget to write.
        $route = app('router')->getRoutes()->getByName('salary.payslip');

        $this->assertNotNull($route);
        $this->assertSame(['period'], $route->parameterNames());
    }

    /* ══════════════════════════════════════════════════════════════════════
       PAYROLL ARITHMETIC
       ══════════════════════════════════════════════════════════════════════ */

    public function test_net_is_earnings_less_deductions_for_every_run(): void
    {
        $this->withDemoData();

        foreach (DemoSalaries::all() as $run) {
            $earnings = Money::zero($run['currency']);
            foreach ($run['earnings'] as $line) {
                $earnings = $earnings->plus($line['amount']);
            }

            $deductions = Money::zero($run['currency']);
            foreach ($run['deductions'] as $line) {
                $deductions = $deductions->plus($line['amount']);
            }

            $this->assertTrue(
                $earnings->minus($deductions)->equals(SalaryPresenter::net($run)),
                "{$run['id']}: net does not reconcile with its lines"
            );
        }
    }

    public function test_the_earning_lines_always_sum_to_the_gross_exactly(): void
    {
        // The last line is the remainder, not its own percentage, so three
        // independently-rounded shares can never leave a stray paisa.
        $this->withDemoData();

        foreach (DemoSalaries::all() as $run) {
            $sum = array_sum(array_map(fn (array $l) => $l['amount']->minor, $run['earnings']));

            $this->assertSame(
                $sum,
                SalaryPresenter::totalEarnings($run)->minor,
                "{$run['id']}: earning lines do not sum to the gross"
            );
        }
    }

    public function test_ctc_is_twelve_times_the_gross_so_it_cannot_contradict_it(): void
    {
        // The handover showed CTC ₹12,60,000, net ₹85,800 and a table figure of
        // ₹80,000 for the same person, with nothing connecting them.
        $this->withDemoData();

        $run = DemoSalaries::latestFor(DemoSalaries::VIEWER);

        $this->assertSame(
            SalaryPresenter::totalEarnings($run)->minor * 12,
            SalaryPresenter::annualCtc($run)->minor
        );
    }

    public function test_no_amount_is_held_as_a_float(): void
    {
        $this->withDemoData();

        foreach (DemoSalaries::all() as $run) {
            foreach ($run['earnings'] as $line) {
                $this->assertInstanceOf(Money::class, $line['amount']);
                $this->assertIsInt($line['amount']->minor);
            }
        }
    }

    public function test_deductions_are_empty_but_the_concept_is_not(): void
    {
        // Decided 2026-08-27: nothing is withheld today. An employee should be
        // able to see that, which is different from not being told.
        $this->withDemoData();

        foreach (DemoSalaries::all() as $run) {
            $this->assertSame([], $run['deductions']);
        }

        $this->get('/salary/mine')->assertSee('Nothing deducted', false);
        $this->get('/salary/mine')->assertSee('your gross is your net', false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       PAYROLL SANITY
       ══════════════════════════════════════════════════════════════════════ */

    public function test_nobody_is_paid_for_a_month_before_they_joined(): void
    {
        $this->withDemoData();

        foreach (DemoSalaries::all() as $run) {
            $joined = \Illuminate\Support\Carbon::parse($run['employee_record']['joined'])->startOfMonth();
            $period = \Illuminate\Support\Carbon::createFromFormat('Y-m', $run['period'])->startOfMonth();

            $this->assertTrue(
                $joined->lessThanOrEqualTo($period),
                "{$run['id']}: a salary run exists for a month before this person joined"
            );
        }
    }

    public function test_one_run_exists_per_person_per_month(): void
    {
        // The shape generating must be idempotent against.
        $this->withDemoData();

        $ids = DemoSalaries::all()->pluck('id');

        $this->assertSame($ids->count(), $ids->unique()->count(), 'a person has two runs in one month');
    }

    public function test_people_missing_from_payroll_are_shown_not_just_the_ones_in_it(): void
    {
        // The handover had no equivalent, and the person missing from a payroll
        // list is the one who does not get paid.
        $this->withDemoData();

        $withoutStructure = DemoSalaries::withoutStructure();

        $this->assertNotEmpty($withoutStructure, 'no sample case for the "no structure" state');

        $html = $this->get('/salary')->getContent();
        $this->assertStringContainsString('Nobody should be missing from payroll', $html);
        $this->assertStringContainsString($withoutStructure->first()['name'], $html);
    }

    public function test_an_unpaid_row_says_so_rather_than_showing_a_dash(): void
    {
        $this->withDemoData();

        $this->get('/salary')->assertSee('Not paid yet', false);
        $this->get('/salary')->assertDontSee('>--<', false);
    }

    public function test_on_hold_reads_as_a_decision_somebody_made(): void
    {
        $this->withDemoData();

        $this->get('/salary')->assertSee('Held back deliberately', false);
        $this->get('/salary/EMP005/'.$this->period())->assertSee('not a system state', false);
    }

    public function test_the_list_states_gross_and_net_separately(): void
    {
        // "Salary" alone does not say which, and it is the figure somebody
        // reconciles against a bank statement.
        $this->withDemoData();

        $response = $this->get('/salary');
        $response->assertSee('Gross', false);
        $response->assertSee('Net pay', false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE USUAL GUARDS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_the_demo_source_is_inert_outside_local_debug(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false]);

        $this->assertFalse(DemoSalaries::enabled());
        $this->assertTrue(DemoSalaries::all()->isEmpty());
        $this->assertTrue(DemoSalaries::missingFrom(now()->format('Y-m'))->isEmpty());
        $this->assertTrue(DemoSalaries::withoutStructure()->isEmpty());
    }

    public function test_no_figure_is_written_into_the_markup(): void
    {
        // The handover hardcoded 28 paid and 12 pending against a table headed
        // "All Salary Records (40)" — for a company of twelve.
        $this->withDemoData();

        $response = $this->get('/salary');

        $response->assertSee('Paid out', false);
        $response->assertDontSee('All Salary Records (40)', false);
        $this->assertLessThanOrEqual(DemoEmployees::all()->count(), DemoSalaries::forPeriod($this->period())->count());
    }

    public function test_an_unknown_run_or_a_malformed_period_is_not_found(): void
    {
        $this->withDemoData();

        $this->get('/salary/EMP999/'.$this->period())->assertNotFound();
        $this->get('/salary/payslip/2026-13')->assertNotFound();
        $this->get('/salary/payslip/1999-01')->assertNotFound();
        $this->get('/salary/'.urlencode('<script>').'/2026-08')->assertNotFound();
    }

    public function test_an_invalid_filter_is_rejected(): void
    {
        $this->get('/salary?period=banana')->assertSessionHasErrors('period');
        $this->get('/salary?status=whatever')->assertSessionHasErrors('status');
    }

    public function test_the_write_routes_exist_so_the_forms_are_real(): void
    {
        $this->assertTrue(app('router')->has('salary.generate'));
        $this->assertTrue(app('router')->has('salary.pay'));
    }

    public function test_the_pages_render_nothing_the_content_security_policy_would_block(): void
    {
        $this->withDemoData();

        foreach (['/salary', '/salary/mine', '/salary/payslip/'.$this->period(), '/salary/EMP005/'.$this->period()] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertSame(0, preg_match_all('/<style[\s>]/i', $html), "inline <style> in {$url}");
            $this->assertSame(0, preg_match_all('/\sstyle="/i', $html), "inline style attribute in {$url}");
            $this->assertSame(0, preg_match_all('/\son[a-z]+="/i', $html), "inline event handler in {$url}");
            $this->assertSame(0, preg_match_all('/<script(?![^>]*\ssrc=)/i', $html), "inline <script> in {$url}");
        }
    }

    public function test_the_sidebar_marks_salary_as_current(): void
    {
        $this->withDemoData();

        foreach (['/salary', '/salary/mine', '/salary/payslip/'.$this->period()] as $url) {
            $this->assertSame(
                1,
                substr_count($this->get($url)->getContent(), 'class="sb-link active"'),
                "sidebar current marker wrong on {$url}"
            );
        }
    }
}
