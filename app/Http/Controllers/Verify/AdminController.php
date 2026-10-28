<?php

namespace App\Http\Controllers\Verify;

use App\Models\CompanyRegistration;
use App\Support\Audit\AuditLog;
use App\Support\Verify\VerifyDirectory;
use App\Support\Verify\VerifySignature;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * verify.zephryxlabs.in/admin (2026-10-29): who is on file, how often their
 * cards are checked, the QR for each card, and the enquiry details.
 */
class AdminController extends Controller
{
    public function __construct(protected AuditLog $audit) {}

    public function dashboard(Request $request): Response
    {
        $search = (string) $request->query('q', '');

        return response()->view('verify.admin', [
            'stats' => VerifyDirectory::stats(),
            'rows' => VerifyDirectory::rows($search),
            'search' => $search,
            'phone' => VerifyDirectory::setting(VerifyDirectory::PHONE),
            'email' => VerifyDirectory::setting(VerifyDirectory::EMAIL),
            'registrations' => CompanyRegistration::orderBy('sort_order')->orderBy('id')->get(),
            'registrationTypes' => CompanyRegistration::TYPES,
        ]);
    }

    /**
     * The address printed on a card: always https on the verify domain, never
     * whatever host or scheme the dashboard happened to be opened on.
     */
    public static function cardUrl(string $employeeId): string
    {
        return 'https://'.config('verify.domain').'/verify/'.$employeeId.'?signature='.VerifySignature::forCard($employeeId);
    }

    /**
     * GET /admin/qr/{employee} - the PNG, downloaded.
     */
    public function qr(Request $request, string $employee): Response
    {
        $record = VerifyDirectory::find($employee);
        abort_if($record === null, 404);

        $id = $record->user->user_id;

        $png = (new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => false,
            'scale' => 12,
            'quietzoneSize' => 2,
        ])))->render(self::cardUrl($id));

        $this->audit->record(
            action: AuditLog::VERIFY_QR_DOWNLOADED,
            actor: $request->user(),
            entityType: 'employee',
            entityId: $id,
            request: $request,
        );

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$id.'-id-card-qr.png"',
        ]);
    }

    /**
     * POST /admin/settings - the Call and Email buttons on every verify page.
     */
    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +()-]*$/'],
            'email' => ['nullable', 'email', 'max:190'],
        ], [
            'phone.regex' => 'Digits, spaces, + ( ) and - only.',
        ]);

        VerifyDirectory::putSetting(VerifyDirectory::PHONE, trim((string) ($data['phone'] ?? '')));
        VerifyDirectory::putSetting(VerifyDirectory::EMAIL, trim((string) ($data['email'] ?? '')));

        $this->audit->record(
            action: AuditLog::VERIFY_SETTINGS_CHANGED,
            actor: $request->user(),
            entityType: 'verify',
            entityId: 'contact',
            after: 'Phone: '.($data['phone'] ?? '-').'; email: '.($data['email'] ?? '-'),
            request: $request,
        );

        return redirect()->route('verify.admin')
            ->with('status', 'Enquiry details saved.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /admin/registrations - add one: a type, its number, and a
     * document if there is one. "Add ID" again for the next.
     */
    public function addRegistration(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(CompanyRegistration::TYPES))],
            'number' => ['required', 'string', 'max:100'],
            'document' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'document.mimes' => 'The document must be a PDF.',
            'document.max' => 'The document must be 10 MB or smaller.',
        ]);

        $registration = CompanyRegistration::create([
            'type' => $data['type'],
            'number' => trim($data['number']),
            'sort_order' => (int) CompanyRegistration::max('sort_order') + 1,
        ]);

        if ($request->hasFile('document')) {
            $path = VerifyDirectory::REGISTRATION_DIR.'/'.$registration->id.'-'.Str::random(16).'.pdf';
            Storage::disk('local')->put($path, $request->file('document')->get());
            $registration->update(['document_path' => $path]);
        }

        $this->audit->record(
            action: AuditLog::VERIFY_SETTINGS_CHANGED,
            actor: $request->user(),
            entityType: 'verify',
            entityId: 'registration',
            after: 'Added '.$registration->label().': '.$registration->number.($registration->document_path ? ' (with document)' : ''),
            request: $request,
        );

        return redirect()->route('verify.admin')
            ->with('status', $registration->label().' added.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /admin/registrations/{registration}/delete
     */
    public function removeRegistration(Request $request, CompanyRegistration $registration): RedirectResponse
    {
        if ($registration->document_path !== null) {
            Storage::disk('local')->delete($registration->document_path);
        }

        $label = $registration->label();
        $registration->delete();

        $this->audit->record(
            action: AuditLog::VERIFY_SETTINGS_CHANGED,
            actor: $request->user(),
            entityType: 'verify',
            entityId: 'registration',
            after: 'Removed '.$label.': '.$registration->number,
            request: $request,
        );

        return redirect()->route('verify.admin')
            ->with('status', $label.' removed.')
            ->with('status_tone', 'success');
    }
}
