<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\Documents\DocumentStore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * The demo profiles, the reporting line, and four files on the private disk.
 * Local + debug only.
 *
 * ═════════════════════════════════════════════════════════════════════════════
 * ONE PERSON HAS A FULL PROFILE AND THE OTHERS DO NOT
 *
 * Deliberate. `employee_profiles` has no row until somebody saves the form, and
 * "never filled it in" is a state the page has to draw — the version where
 * every seeded person has every field is the version where the empty case is
 * never seen until a real employee opens it on their first day.
 *
 * THE DOCUMENTS ARE REAL FILES, NOT JUST ROWS
 *
 * A row pointing at nothing makes the download route look built and 404 on
 * every click, which is the shape of bug that survives a whole review. So this
 * writes four small placeholder PDFs to the private disk under the same folder
 * the upload uses, and the rows point at them. They are text in a PDF wrapper —
 * the point is that the route streams a file that exists, not that anybody
 * reads it.
 *
 * Nothing about them is anybody's real identity document, which is worth saying
 * out loud on the one seeder that creates files named "Aadhaar card".
 * ═════════════════════════════════════════════════════════════════════════════
 */
class ProfileSeeder extends Seeder
{
    /**
     * Who reports to whom, by staff id.
     *
     * A shallow tree on purpose: two leads and the rest under them. A deep one
     * would make the profile header's single "Reporting to" row look like it
     * was hiding something.
     *
     * @var array<string, string>
     */
    protected const REPORTS_TO = [
        'EMP002' => 'EMP001',
        'EMP004' => 'EMP001',
        'EMP010' => 'EMP002',
        'EMP003' => 'EMP001',
        'EMP009' => 'EMP003',
        'EMP007' => 'EMP005',
        'EMP008' => 'EMP001',
        'EMP011' => 'EMP005',
        'EMP012' => 'EMP001',
    ];

    public function run(): void
    {
        if (! (app()->environment('local') && config('app.debug'))) {
            return;
        }

        $employees = Employee::query()
            ->with('user')
            ->get()
            ->keyBy(fn (Employee $e) => (string) $e->user?->user_id);

        $this->reportingLine($employees);
        $this->profile($employees->get('EMP002'));
        $this->documents($employees->get('EMP002'));
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Employee>  $employees
     */
    protected function reportingLine($employees): void
    {
        foreach (self::REPORTS_TO as $staffId => $managerStaffId) {
            $employee = $employees->get($staffId);
            $manager = $employees->get($managerStaffId);

            if ($employee === null || $manager === null) {
                continue;
            }

            $employee->update(['reports_to' => $manager->id]);
        }
    }

    /**
     * One filled-in profile. See the head of this class for why only one.
     */
    protected function profile(?Employee $employee): void
    {
        if ($employee === null) {
            return;
        }

        EmployeeProfile::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'phone' => '+91 98200 55667',
                'address' => "14B Southern Avenue\nKolkata, West Bengal 700029\nIndia",
                'gender' => 'Male',
                'marital_status' => 'Single',
                'nationality' => 'Indian',
                'languages' => ['English', 'Hindi', 'Bengali'],
                'skills' => ['Laravel', 'Vue', 'MySQL', 'Accessibility', 'Code review'],
                'emergency_name' => 'Sunita Verma',
                'emergency_relationship' => 'Mother',
                'emergency_phone' => '+91 98200 11002',
                'notify_tasks' => true,
                'notify_tickets' => true,
            ],
        );
    }

    /**
     * Four files, on the disk and in the table.
     *
     * `uploaded_by` is the person themselves on three and null on the offer
     * letter, because HR put that one there — which is the distinction the rail
     * draws, and the only one it draws.
     */
    protected function documents(?Employee $employee): void
    {
        if ($employee === null) {
            return;
        }

        $rows = [
            ['DOC-0011', 'Resume.pdf', 'resume', 540, true],
            ['DOC-0012', 'PAN card.pdf', 'identity', 538, true],
            ['DOC-0013', 'Aadhaar card.pdf', 'identity', 538, true],
            ['DOC-0014', 'Offer letter.pdf', 'employment', 545, false],
        ];

        $folder = 'employees/'.$employee->id.'/documents';

        foreach ($rows as [$reference, $name, $kind, $daysAgo, $bySelf]) {
            $path = $folder.'/seed-'.strtolower(str_replace(['-', '_'], '', $reference)).'.pdf';

            /*
             * The private disk — `storage/app/private`, outside the webroot.
             * The same disk DocumentStore writes to, reached through it rather
             * than by naming the disk here, so a change to where documents live
             * moves the seed too.
             */
            Storage::disk('local')->put($path, $this->placeholderPdf($name));

            EmployeeDocument::updateOrCreate(
                ['reference' => $reference],
                [
                    'employee_id' => $employee->id,
                    'name' => $name,
                    'kind' => $kind,
                    'path' => $path,
                    'bytes' => Storage::disk('local')->size($path),
                    'mime' => 'application/pdf',
                    'uploaded_by' => $bySelf ? $employee->user_id : null,
                ],
            )->forceFill([
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo),
            ])->saveQuietly();
        }

        // Second line, in case the folder above ever stops matching what the
        // upload composes: a seed whose files land somewhere the route does not
        // look would leave every download 404ing with rows that look fine.
        $missing = EmployeeDocument::where('employee_id', $employee->id)
            ->get()
            ->reject(fn (EmployeeDocument $d) => app(DocumentStore::class)->exists($d->path));

        if ($missing->isNotEmpty()) {
            $this->command?->warn('ProfileSeeder wrote rows whose files are not on the disk.');
        }
    }

    /**
     * The smallest thing a PDF reader will open.
     *
     * Written out rather than copied from a fixture file so the seeder has no
     * binary assets to keep in the repository — and so it is obvious at a
     * glance that nothing here is anybody's real document.
     */
    protected function placeholderPdf(string $title): string
    {
        $stream = 'BT /F1 12 Tf 60 760 Td (Sample document: '.$title.'. Development data only.) Tj ET';

        return "%PDF-1.4\n"
            ."1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R>>endobj\n"
            .'4 0 obj<</Length '.strlen($stream).">>stream\n".$stream."\nendstream endobj\n"
            ."trailer<</Root 1 0 R>>\n%%EOF\n";
    }
}
