<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeBanking;
use App\Models\SalaryRecord;
use App\Support\Demo\DemoSalaries;
use App\Support\IdProof;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The demo banking details and salary records. Local + debug only.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TWO STATES ARE PRESERVED ON PURPOSE, BECAUSE THEY ARE THE INTERESTING ONES
 *
 * EMP011 gets no banking details: a recent joiner nobody has set up, who
 * therefore cannot be paid at all. And the current month is left mid-run — some
 * payslips added and paid, some added and awaiting payment, some with nothing
 * added — so all three states are reviewable on one screen.
 *
 * The payslip FILE is not seeded. There is no document to invent, and a record
 * whose `payslip_path` pointed at nothing would break the download route rather
 * than demonstrate it. The demo records carry the figure and the metadata; a
 * real file arrives when somebody uploads one.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class SalarySeeder extends Seeder
{
    public function run(): void
    {
        if (! (app()->environment('local') && config('app.debug'))) {
            return;
        }

        $employees = Employee::query()
            ->with('user')
            ->get()
            ->keyBy(fn (Employee $e) => (string) $e->user?->user_id);

        $this->banking($employees);
        $this->records($employees);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Employee>  $employees
     */
    protected function banking($employees): void
    {
        foreach ($employees as $staffId => $employee) {
            $details = DemoSalaries::banked($staffId);

            if ($details === null) {
                // EMP011, deliberately. See the head of this class.
                continue;
            }

            EmployeeBanking::updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'bank_name' => $details['bank'],
                    'ifsc' => $details['ifsc'],
                    'account_number' => $details['account'],
                    'pan' => $details['pan'],
                    /*
                     * The document each person actually produced, from the
                     * fixture rather than assumed. It was hardcoded to Aadhaar
                     * while the fixtures held nothing else, which made every
                     * masked row on every page read the same and hid whether
                     * the other three masking rules worked at all.
                     *
                     * Still stated rather than inferred from the number: a
                     * number whose document cannot be named is withheld from
                     * every screen.
                     */
                    'id_proof_type' => $details['id_proof_type'] ?? IdProof::AADHAAR,
                    'id_proof_number' => $details['id_proof_number'],
                    // Null for some people on purpose — the photocopy arrives
                    // on paper, and "Not yet" is a state HR has to chase.
                    'id_proof_copy_received_on' => isset($details['copy_received'])
                        ? Carbon::parse($details['copy_received'])
                        : null,
                ],
            );
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Employee>  $employees
     */
    protected function records($employees): void
    {
        foreach (DemoSalaries::all() as $row) {
            $employee = $employees->get($row['employee']);

            if ($employee === null) {
                continue;
            }

            $hasPayslip = $row['payslip'] !== null;

            SalaryRecord::updateOrCreate(
                ['employee_id' => $employee->id, 'period' => $row['period']],
                [
                    'net_minor' => $row['net']?->minor,
                    'currency' => $row['currency'],
                    // Metadata without a file: the record says a payslip was
                    // added, and the download route correctly finds nothing to
                    // hand over. A path pointing at a missing file would be the
                    // dishonest version.
                    'payslip_path' => null,
                    'payslip_name' => $hasPayslip ? $row['payslip']['name'] : null,
                    'payslip_bytes' => $hasPayslip ? 69632 : null,
                    'payslip_added_at' => $hasPayslip ? Carbon::parse($row['payslip']['added_on'])->setTime(11, 0) : null,
                    'payslip_added_by' => $hasPayslip ? $employees->get('EMP005')?->id : null,
                    'paid_on' => $row['paid_on'],
                    'method' => $row['method'],
                    'paid_by' => $row['paid_on'] ? $employees->get('EMP005')?->id : null,
                ],
            );
        }
    }
}
