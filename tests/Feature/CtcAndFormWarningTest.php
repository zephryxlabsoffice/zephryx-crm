<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSalaryStructure;
use App\Support\IdProof;
use App\Support\SalaryStructure;
use Tests\TestCase;

/**
 * CTC worked out or typed yearly or monthly, and the warning a failed form puts
 * at the top of the page (owner, 2026-11-02).
 */
class CtcAndFormWarningTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function hire(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Asha Roy',
            'personal_email' => 'asha@gmail.test',
            'employment_type' => Employee::FULL_TIME,
            'joined_on' => now()->subMonth()->toDateString(),
            'phone' => '9800000000',
            'current_address' => "1 Somewhere Road\nKolkata",
            'permanent_address' => "2 Elsewhere Lane\nHowrah",
            'id_proof_type' => IdProof::AADHAAR,
            'id_proof_number' => '123456781234',
            'pan' => 'ABCDE1234F',
            'bank_name' => 'HDFC Bank',
            'ifsc' => 'HDFC0001234',
            'account_number' => '50100234567890',
            'basic' => '60000', 'hra' => '24000', 'allowances' => '6000',
            'pf' => '1800', 'pt' => '200', 'tds' => '5000',
        ];
    }

    protected function hired(): Employee
    {
        return Employee::with('user')
            ->whereHas('user', fn ($q) => $q->where('name', 'Asha Roy'))
            ->firstOrFail();
    }

    protected function structure(Employee $employee): EmployeeSalaryStructure
    {
        return EmployeeSalaryStructure::where('employee_id', $employee->id)->firstOrFail();
    }

    public function test_an_empty_ctc_is_calculated_from_earnings_without_deductions(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->hire(['ctc' => '']))->assertSessionHasNoErrors();

        $employee = $this->hired();

        $this->assertNull($this->structure($employee)->ctc_minor);

        // (60,000 + 24,000 + 6,000) x 12, with the deductions left out.
        $this->get('/employees/'.$employee->user->user_id)
            ->assertOk()
            ->assertSee('10,80,000')
            ->assertSee('90,000')
            ->assertSee('(calculated)');
    }

    public function test_a_calculated_ctc_follows_the_pay_when_it_changes(): void
    {
        $structure = new EmployeeSalaryStructure([
            'kind' => EmployeeSalaryStructure::BREAKDOWN,
            'basic_minor' => 5000000,
        ]);

        $this->assertSame(60000000, SalaryStructure::calculatedCtcMinor($structure));

        $structure->basic_minor = 6000000;

        $this->assertSame(72000000, SalaryStructure::calculatedCtcMinor($structure));
    }

    public function test_a_stipend_is_calculated_and_a_freelance_rate_is_not(): void
    {
        $this->assertSame(12000000, SalaryStructure::calculatedCtcMinor(new EmployeeSalaryStructure([
            'kind' => EmployeeSalaryStructure::STIPEND,
            'stipend_minor' => 1000000,
        ])));

        $this->assertNull(SalaryStructure::calculatedCtcMinor(new EmployeeSalaryStructure([
            'kind' => EmployeeSalaryStructure::RATE,
            'rate_minor' => 1000000,
        ])));
    }

    public function test_a_monthly_amount_is_stored_per_year_and_a_yearly_one_as_typed(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->hire(['ctc' => '100000', 'ctc_period' => 'monthly']))
            ->assertSessionHasNoErrors();

        $employee = $this->hired();

        $this->assertSame(120000000, (int) $this->structure($employee)->ctc_minor);

        $page = $this->get('/employees/'.$employee->user->user_id)->assertOk();
        $page->assertSee('12,00,000')->assertSee('1,00,000')->assertDontSee('(calculated)');

        $this->post('/employees/'.$employee->user->user_id, ['ctc' => '1260000', 'ctc_period' => 'yearly'] + $this->hire());

        $this->assertSame(126000000, (int) $this->structure($employee)->fresh()->ctc_minor);
    }

    public function test_emptying_a_typed_ctc_hands_it_back_to_the_calculation(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->hire(['ctc' => '1260000']))->assertSessionHasNoErrors();

        $employee = $this->hired();
        $this->assertNotNull($this->structure($employee)->ctc_minor);

        $this->post('/employees/'.$employee->user->user_id, ['ctc' => ''] + $this->hire());

        $this->assertNull($this->structure($employee)->fresh()->ctc_minor);
    }

    public function test_a_period_outside_the_two_is_refused(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->post('/employees', $this->hire(['ctc' => '100', 'ctc_period' => 'weekly']))
            ->assertSessionHasErrors('ctc_period');
    }

    public function test_a_failed_form_warns_at_the_top_and_keeps_what_was_typed(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->from('/employees/create')
            ->post('/employees', $this->hire(['ctc' => 'lots', 'ctc_period' => 'monthly', 'name' => 'Kept Name', 'phone' => '']))
            ->assertRedirect('/employees/create');

        $page = $this->get('/employees/create')->assertOk();

        $page->assertSeeInOrder([
            'That was not saved. Please fix the following and submit again.',
            'The CTC must be a number, like 25000 or 25000.50.',
            'What you typed is still in the form.',
            'name="name"',
        ], false);

        $page->assertSee('value="Kept Name"', false);
        $page->assertSee('value="lots"', false);
        $page->assertSee('<option value="monthly" selected', false);

        $this->get('/employees/create')->assertDontSee('That was not saved.');
    }

    public function test_a_page_with_no_failed_form_shows_no_warning(): void
    {
        $this->signInAsStaff(['employee', 'hr']);

        $this->get('/employees/create')->assertOk()->assertDontSee('That was not saved.');
    }
}
