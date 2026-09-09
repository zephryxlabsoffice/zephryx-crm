<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeBanking;
use App\Models\Role;
use App\Models\User;
use App\Support\Rbac\Rbac;
use App\Support\SalaryDirectory;
use App\Support\Money;
use App\Support\SalaryPresenter as P;
use Tests\TestCase;

class SalaryPageTest extends TestCase
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
    /** The demo person these pages are read as. */
    protected const VIEWER = 'EMP002';

    /**
     * The demo pay as real rows, read as somebody who has some.
     *
     * `/salary/mine` and `/salary/payslip/{period}` resolve the person from the
     * session — that is the whole design of the route — so a test about
     * somebody's own pay has to be somebody with pay. EMP002 with HR alongside
     * Employee: their own payslips, and the permission to run payroll for
     * everybody else.
     */
    protected function withDemoData(): void
    {
        $this->seedDemoWorkforce();

        $user = User::where('user_id', self::VIEWER)->firstOrFail();
        $user->roles()->syncWithoutDetaching(Role::whereIn('role_key', ['employee', 'hr'])->pluck('id'));

        app(Rbac::class)->forget($user);
        $this->actingAs($user);
    }

    protected function viewer(): Employee
    {
        return Employee::whereHas('user', fn ($q) => $q->where('user_id', self::VIEWER))->firstOrFail();
    }

    protected function period(): string
    {
        return now()->format('Y-m');
    }

    /**
     * Every record across the months the demo covers.
     *
     * Walked period by period rather than read straight off the table, because
     * a payroll month is a list of PEOPLE with records looked up against them —
     * the rows for somebody with no record are the ones worth seeing.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function allRecords(): \Illuminate\Support\Collection
    {
        return collect(range(0, 5))
            ->flatMap(fn (int $back) => SalaryDirectory::forPeriod(
                now()->startOfMonth()->subMonths($back)->format('Y-m')
            ))
            ->values();
    }

    /**
     * POST with a real CSRF token.
     *
     * `withDemoData()` moves the environment to `local`, which switches CSRF
     * verification back on — Laravel only skips it while the app reports it is
     * running tests. Rather than disabling the middleware, these posts carry a
     * token, so the tests exercise the same path a browser does and a form that
     * forgot `@csrf` would fail here.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function postForm(string $url, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->withSession(['_token' => 'test-token'])
            ->post($url, $payload + ['_token' => 'test-token']);
    }

    public function test_the_pages_render(): void
    {
        $this->withDemoData();

        $this->get('/salary')->assertOk()->assertSee('Salary Management', false);
        $this->get('/salary/mine')->assertOk()->assertSee('My Salary', false);
        $this->get('/salary/payslip/'.$this->period())->assertOk();
        $this->get('/salary/EMP004/'.$this->period())->assertOk();
    }

    public function test_mine_and_payslip_are_not_read_as_employee_references(): void
    {
        $this->withDemoData();

        $this->get('/salary/mine')->assertOk();
        $this->get('/salary/payslip/'.$this->period())->assertOk();
    }

    /* ══════════════════════════════════════════════════════════════════════
       THIS APPLICATION DOES NOT CALCULATE PAYROLL

       Decided 2026-08-27: pay is worked out in Excel, and later by payroll
       software over an API. Holding a second version of somebody else's
       calculation is how two numbers for one salary come to exist.
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_record_holds_the_payslip_and_the_net_and_nothing_derived(): void
    {
        $this->withDemoData();

        foreach ($this->allRecords() as $record) {
            $this->assertArrayNotHasKey('earnings', $record);
            $this->assertArrayNotHasKey('deductions', $record);
            $this->assertArrayNotHasKey('structure', $record);
            $this->assertArrayNotHasKey('gross', $record);
        }
    }

    public function test_the_presenter_no_longer_computes_pay(): void
    {
        // If any of these come back, the question to ask is where the figures
        // are coming from and which system owns them.
        $this->assertFalse(method_exists(P::class, 'totalEarnings'));
        $this->assertFalse(method_exists(P::class, 'totalDeductions'));
        $this->assertFalse(method_exists(P::class, 'annualCtc'));
    }

    public function test_no_page_shows_a_gross_figure_or_a_ctc(): void
    {
        $this->withDemoData();

        foreach (['/salary', '/salary/mine', '/salary/payslip/'.$this->period(), '/salary/EMP004/'.$this->period()] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertStringNotContainsString('>Gross<', $html, "a gross column in {$url}");
            $this->assertStringNotContainsStringIgnoringCase('Annual CTC', $html, "a CTC figure in {$url}");
            $this->assertStringNotContainsString('House rent allowance', $html);
        }
    }

    public function test_the_portal_has_no_pay_for_this_month_card(): void
    {
        // The month's figure is the first row of the history, and the breakdown
        // is in the payslip. Restating either would be a second place to keep
        // correct.
        $this->withDemoData();

        $html = $this->get('/salary/mine')->getContent();

        $this->assertStringNotContainsString('Pay for this month', $html);
        $this->assertStringContainsString('Salary History', $html);
    }

    public function test_a_missing_net_reads_as_not_recorded_rather_than_zero(): void
    {
        // A zero is a claim that somebody was paid nothing.
        $this->withDemoData();

        $record = $this->allRecords()->first(fn (array $r) => $r['payslip'] === null);

        $this->assertNotNull($record, 'no sample record without a payslip');
        $this->assertNull($record['net']);
        $this->assertSame('Not recorded', P::net($record));

        $this->get('/salary')->assertSee('Not recorded', false);
    }

    /* ══════════════════════════════════════════════════════════════════════
       STATUS, AND WHAT CAN BE PAID
       ══════════════════════════════════════════════════════════════════════ */

    public function test_status_falls_out_of_the_payslip_and_the_payment(): void
    {
        $this->withDemoData();

        foreach ($this->allRecords() as $record) {
            $expected = match (true) {
                $record['payslip'] === null => P::NO_PAYSLIP,
                $record['paid_on'] === null => P::AWAITING_PAYMENT,
                default => P::PAID,
            };

            $this->assertSame($expected, P::statusOf($record), "{$record['id']}: wrong status");
        }
    }

    public function test_nobody_is_paid_without_a_payslip_on_file(): void
    {
        // Nothing to check the amount against, and nothing to give them if they
        // ask what they were paid for.
        $this->withDemoData();

        foreach ($this->allRecords() as $record) {
            if ($record['paid_on'] !== null) {
                $this->assertNotNull($record['payslip'], "{$record['id']}: paid with no payslip");
                $this->assertNotNull($record['net'], "{$record['id']}: paid with no net recorded");
            }
        }
    }

    public function test_only_a_record_awaiting_payment_is_payable(): void
    {
        $this->withDemoData();

        foreach ($this->allRecords() as $record) {
            $this->assertSame(
                P::statusOf($record) === P::AWAITING_PAYMENT,
                P::isPayable($record),
                "{$record['id']}: payable does not match status"
            );
        }
    }

    public function test_only_payable_rows_get_a_checkbox(): void
    {
        // A greyed-out box invites the click anyway; an absent one does not.
        $this->withDemoData();

        $html = $this->get('/salary')->getContent();
        $payable = SalaryDirectory::forPeriod($this->period())->filter(fn (array $r) => P::isPayable($r));
        $notPayable = SalaryDirectory::forPeriod($this->period())->reject(fn (array $r) => P::isPayable($r));

        $this->assertNotEmpty($payable);
        $this->assertNotEmpty($notPayable);

        foreach ($payable as $record) {
            $this->assertStringContainsString('value="'.$record['employee'].'"', $html);
        }

        foreach ($notPayable as $record) {
            $this->assertStringNotContainsString('id="pay-'.$record['employee'].'"', $html);
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE TWO-STEP BULK FLOW
       ══════════════════════════════════════════════════════════════════════ */

    public function test_selecting_rows_reaches_a_confirmation_rather_than_marking_anyone_paid(): void
    {
        $this->withDemoData();

        $payable = SalaryDirectory::forPeriod($this->period())
            ->filter(fn (array $r) => P::isPayable($r))
            ->pluck('employee')
            ->all();

        $response = $this->postForm('/salary/pay/confirm', [
            'period' => $this->period(),
            'employees' => $payable,
        ]);

        $response->assertOk();
        $response->assertSee('Confirm payment', false);
        $response->assertSee('About to be marked paid', false);
        // The commit button is on the confirmation, not on the list.
        $response->assertSee('Yes, mark', false);
    }

    public function test_the_confirmation_names_everybody_and_totals_them(): void
    {
        $this->withDemoData();

        $payable = SalaryDirectory::forPeriod($this->period())->filter(fn (array $r) => P::isPayable($r));

        $response = $this->postForm('/salary/pay/confirm', [
            'period' => $this->period(),
            'employees' => $payable->pluck('employee')->all(),
        ]);

        foreach ($payable as $record) {
            $response->assertSee($record['employee_record']['name'], false);
        }

        $total = Money::zero();
        foreach ($payable as $record) {
            $total = $total->plus($record['net']);
        }

        $response->assertSee($total->short(), false);
    }

    public function test_a_row_that_cannot_be_paid_is_dropped_from_the_confirmation_and_the_gap_is_stated(): void
    {
        // Somebody ticks, then a colleague pays one of them first. The
        // confirmation must not promise something it cannot do, and must not
        // silently shrink either.
        $this->withDemoData();

        $notPayable = SalaryDirectory::forPeriod($this->period())
            ->reject(fn (array $r) => P::isPayable($r))
            ->first();

        $payable = SalaryDirectory::forPeriod($this->period())
            ->filter(fn (array $r) => P::isPayable($r))
            ->first();

        $response = $this->postForm('/salary/pay/confirm', [
            'period' => $this->period(),
            'employees' => [$payable['employee'], $notPayable['employee']],
        ]);

        $response->assertOk();
        $response->assertSee($payable['employee_record']['name'], false);
        $response->assertSee('1 row left out', false);
    }

    public function test_a_confirmation_with_nothing_payable_says_so_and_offers_no_commit(): void
    {
        $this->withDemoData();

        $notPayable = SalaryDirectory::forPeriod($this->period())
            ->reject(fn (array $r) => P::isPayable($r))
            ->first();

        $response = $this->postForm('/salary/pay/confirm', [
            'period' => $this->period(),
            'employees' => [$notPayable['employee']],
        ]);

        $response->assertOk();
        $response->assertSee('Nothing here can be marked paid', false);
        $response->assertDontSee('Yes, mark', false);
    }

    public function test_the_confirmation_refuses_a_malformed_request(): void
    {
        $this->withDemoData();

        $this->postForm('/salary/pay/confirm', ['period' => 'banana', 'employees' => ['EMP002']])
            ->assertSessionHasErrors('period');

        $this->postForm('/salary/pay/confirm', ['period' => $this->period()])
            ->assertSessionHasErrors('employees');

        $this->postForm('/salary/pay/confirm', ['period' => $this->period(), 'employees' => []])
            ->assertSessionHasErrors('employees');
    }

    public function test_the_confirmation_is_a_post_so_a_dozen_ids_never_reach_a_url(): void
    {
        $route = app('router')->getRoutes()->getByName('salary.pay.confirm');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertNotContains('GET', $route->methods());
    }

    /* ══════════════════════════════════════════════════════════════════════
       SENSITIVE DATA

       A leak here is somebody's bank account or government ID. These assert
       against rendered HTML, because the question is what reached the browser.
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * @return list<string>
     */
    protected function everySecret(): array
    {
        /*
         * Read from the banking table itself, not from a salary row.
         *
         * The row shape no longer carries identifiers at all — it used to, and
         * that was the demo source handing every page a full account number and
         * trusting the template not to print it. The values still have to come
         * from somewhere in order to be searched for, and the only honest
         * "somewhere" is the source of truth.
         */
        $secrets = [];

        foreach (EmployeeBanking::all() as $banking) {
            $secrets[] = $banking->account_number;
            $secrets[] = $banking->pan;
            $secrets[] = $banking->aadhaar;
        }

        return array_values(array_filter(array_unique($secrets)));
    }

    public function test_the_payroll_list_contains_no_bank_pan_or_aadhaar_at_all(): void
    {
        // Not masked — absent. A masked value on a list of everybody still
        // confirms an account exists and hands over twelve people's last four
        // digits at once.
        $this->withDemoData();

        $html = $this->get('/salary')->getContent();

        foreach ($this->everySecret() as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }

        $this->assertStringNotContainsString('••••', $html);
        $this->assertStringNotContainsString('XXXX XXXX', $html);
    }

    public function test_another_persons_record_carries_none_of_their_identifiers(): void
    {
        $this->withDemoData();

        $html = $this->get('/salary/EMP004/'.$this->period())->getContent();

        foreach ($this->everySecret() as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }

        $this->assertStringNotContainsString('••••', $html);
        $this->assertStringContainsString('are not shown here', $html);
    }

    public function test_the_confirmation_page_shows_no_bank_details_either(): void
    {
        // It is a list of people about to be paid — precisely the screen where
        // somebody might think account numbers belong.
        $this->withDemoData();

        $payable = SalaryDirectory::forPeriod($this->period())
            ->filter(fn (array $r) => P::isPayable($r))
            ->pluck('employee')
            ->all();

        $html = $this->postForm('/salary/pay/confirm', [
            'period' => $this->period(),
            'employees' => $payable,
        ])->getContent();

        foreach ($this->everySecret() as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }

        $this->assertStringNotContainsString('••••', $html);
    }

    public function test_your_own_page_shows_masked_values_and_never_the_full_ones(): void
    {
        $this->withDemoData();

        $viewer = self::VIEWER;
        $banking = SalaryDirectory::banked($this->viewer());

        foreach (['/salary/mine', '/salary/payslip/'.$this->period()] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertStringNotContainsString($banking['account'], $html, "full account number in {$url}");
            $this->assertStringNotContainsString($banking['pan'], $html, "full PAN in {$url}");
            $this->assertStringNotContainsString($banking['aadhaar'], $html, "full Aadhaar in {$url}");

            $this->assertStringContainsString('•••• •••• '.substr($banking['account'], -4), $html);
            $this->assertStringContainsString('XXXX XXXX '.substr($banking['aadhaar'], -4), $html);
        }
    }

    public function test_no_identifier_is_hidden_in_an_attribute_rather_than_omitted(): void
    {
        // The specific failure this guards: masking in the browser instead of
        // in PHP.
        $this->withDemoData();

        foreach (['/salary', '/salary/mine', '/salary/EMP004/'.$this->period(), '/salary/payslip/'.$this->period()] as $url) {
            $html = $this->get($url)->getContent();

            foreach ($this->everySecret() as $secret) {
                $this->assertStringNotContainsString($secret, $html, "{$secret} appears anywhere in {$url}");
            }
        }
    }

    public function test_the_own_payslip_route_takes_no_employee_to_tamper_with(): void
    {
        $route = app('router')->getRoutes()->getByName('salary.payslip');

        $this->assertNotNull($route);
        $this->assertSame(['period'], $route->parameterNames());
    }

    /* ══════════════════════════════════════════════════════════════════════
       PAYROLL SANITY
       ══════════════════════════════════════════════════════════════════════ */

    public function test_nobody_has_a_record_for_a_month_before_they_joined(): void
    {
        $this->withDemoData();

        foreach ($this->allRecords() as $record) {
            $joined = \Illuminate\Support\Carbon::parse($record['employee_record']['joined'])->startOfMonth();
            $period = \Illuminate\Support\Carbon::createFromFormat('Y-m', $record['period'])->startOfMonth();

            $this->assertTrue($joined->lessThanOrEqualTo($period), "{$record['id']}: record predates joining");
        }
    }

    public function test_one_record_exists_per_person_per_month(): void
    {
        $this->withDemoData();

        $ids = $this->allRecords()->pluck('id');

        $this->assertSame($ids->count(), $ids->unique()->count(), 'a person has two records in one month');
    }

    public function test_people_missing_from_payroll_are_shown_not_just_the_ones_in_it(): void
    {
        // A list of everyone being paid says nothing about the person who is
        // not on it, and that person is the one who quietly does not get paid.
        $this->withDemoData();

        $withoutBanking = SalaryDirectory::withoutBanking();

        $this->assertNotEmpty($withoutBanking, 'no sample case for the "no bank details" state');

        $html = $this->get('/salary')->getContent();
        $this->assertStringContainsString('Nobody should be missing from payroll', $html);
        $this->assertStringContainsString($withoutBanking->first()['name'], $html);
        $this->assertStringContainsString('cannot be paid at all', $html);
    }

    public function test_an_unpaid_row_says_so_rather_than_showing_a_dash(): void
    {
        $this->withDemoData();

        $this->get('/salary')->assertSee('Not paid yet', false);
        $this->get('/salary')->assertDontSee('>--<', false);
    }

    public function test_every_amount_is_money_not_a_float(): void
    {
        $this->withDemoData();

        foreach ($this->allRecords() as $record) {
            if ($record['net'] !== null) {
                $this->assertInstanceOf(Money::class, $record['net']);
                $this->assertIsInt($record['net']->minor);
            }
        }
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE USUAL GUARDS
       ══════════════════════════════════════════════════════════════════════ */

    public function test_an_empty_database_produces_an_empty_module(): void
    {
        /*
         * This replaced "the demo source is inert outside local + debug", which
         * was true only because the fixture switched itself off. Pay comes from
         * a table now, and in production real records SHOULD be shown.
         *
         * What survives is the guarantee underneath it: no figure is invented.
         * With no employees there is no payroll — not a payroll of zeroes.
         */
        $this->assertTrue($this->allRecords()->isEmpty());
        $this->assertTrue(SalaryDirectory::missingFrom(now()->format('Y-m'))->isEmpty());
        $this->assertTrue(SalaryDirectory::withoutBanking()->isEmpty());
        $this->assertNull(SalaryDirectory::find(now()->format('Y-m'), 'EMP002'));
    }

    public function test_no_figure_is_written_into_the_markup(): void
    {
        // The handover hardcoded 28 paid and 12 pending against a table headed
        // "All Salary Records (40)" — for a company of twelve.
        $this->withDemoData();

        $response = $this->get('/salary');

        $response->assertSee('Paid out', false);
        $response->assertDontSee('All Salary Records (40)', false);
    }

    public function test_an_unknown_record_or_a_malformed_period_is_not_found(): void
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
        $this->assertTrue(app('router')->has('salary.pay'));
        $this->assertTrue(app('router')->has('salary.pay.confirm'));
        $this->assertTrue(app('router')->has('salary.payslip.store'));
        $this->assertTrue(app('router')->has('salary.payslip.download'));
    }

    public function test_no_route_deletes_a_salary_record(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'salary')) {
                $this->assertNotContains('DELETE', $route->methods(), "a DELETE route exists at {$route->uri()}");
            }
        }
    }

    public function test_my_salary_has_a_way_back_to_payroll(): void
    {
        $this->withDemoData();

        $this->get('/salary/mine')->assertSee('class="back-link"', false);
    }

    public function test_the_pages_render_nothing_the_content_security_policy_would_block(): void
    {
        $this->withDemoData();

        foreach (['/salary', '/salary/mine', '/salary/payslip/'.$this->period(), '/salary/EMP007/'.$this->period()] as $url) {
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
