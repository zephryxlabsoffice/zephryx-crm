<?php

namespace App\Http\Controllers\Verify;

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
            'registrationAt' => VerifyDirectory::setting(VerifyDirectory::REGISTRATION),
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
     * POST /admin/registration - replace (or remove) the company registration PDF.
     */
    public function registration(Request $request): RedirectResponse
    {
        if ($request->boolean('remove')) {
            Storage::disk('local')->delete(VerifyDirectory::REGISTRATION_PATH);
            VerifyDirectory::putSetting(VerifyDirectory::REGISTRATION, null);
            $after = 'Removed';
        } else {
            $request->validate([
                'registration' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            ], [
                'registration.mimes' => 'Upload a PDF.',
                'registration.max' => 'The PDF must be 10 MB or smaller.',
            ]);

            Storage::disk('local')->put(VerifyDirectory::REGISTRATION_PATH, $request->file('registration')->get());
            VerifyDirectory::putSetting(VerifyDirectory::REGISTRATION, now()->toDateTimeString());
            $after = 'Uploaded';
        }

        $this->audit->record(
            action: AuditLog::VERIFY_SETTINGS_CHANGED,
            actor: $request->user(),
            entityType: 'verify',
            entityId: 'registration',
            after: 'Company registration PDF: '.$after,
            request: $request,
        );

        return redirect()->route('verify.admin')
            ->with('status', 'Company registration PDF '.strtolower($after).'.')
            ->with('status_tone', 'success');
    }
}
