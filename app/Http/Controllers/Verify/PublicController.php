<?php

namespace App\Http\Controllers\Verify;

use App\Models\CompanyRegistration;
use App\Support\Documents\DocumentStore;
use App\Support\Verify\VerifyDirectory;
use App\Support\Verify\VerifySignature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * verify.zephryxlabs.in, the public half (2026-10-29). See VerifyDirectory
 * for what is shown and VerifySignature for why every page needs a signature.
 */
class PublicController extends Controller
{
    public function landing(): Response
    {
        return response()->view('verify.landing', $this->contact());
    }

    /**
     * POST /search - an Employee ID typed into the box. A found ID is handed a
     * short-lived signed link rather than shown here, so the page a person
     * lands on is the same one a QR opens.
     */
    public function search(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{3,32}$/'],
        ], [
            'employee_id.required' => 'Type the Employee ID printed on the card.',
            'employee_id.regex' => 'That does not look like an Employee ID. Check it against the card.',
        ]);

        $employee = VerifyDirectory::find($data['employee_id']);

        if ($employee === null) {
            return back()->withInput()->withErrors([
                'employee_id' => 'Nobody with that Employee ID works, or has worked, at '.config('zephryx.brand.name').'.',
            ]);
        }

        $id = $employee->user->user_id;

        return redirect()->route('verify.show', ['employee' => $id] + VerifySignature::forSearch($id));
    }

    /**
     * GET /verify/{employee}?signature=… (a QR) or ?expires=…&signature=… (a search).
     */
    public function show(Request $request, string $employee): Response
    {
        $source = VerifySignature::check($employee, $request->query('signature'), $request->query('expires'));

        if ($source === null) {
            return response()->view('verify.invalid', ['reason' => 'signature'] + $this->contact(), 403);
        }

        $record = VerifyDirectory::find($employee);

        if ($record === null) {
            return response()->view('verify.invalid', ['reason' => 'missing'] + $this->contact(), 404);
        }

        VerifyDirectory::recordScan($record, $source, $request);

        $card = VerifyDirectory::card($record);

        return response()->view('verify.show', [
            'card' => $card,
            // The photo goes out under the same signature as the page.
            'photoUrl' => ($card['has_photo'] ?? false)
                ? route('verify.photo', ['employee' => $record->user->user_id] + $request->only(['expires', 'signature']))
                : null,
            'checkedAt' => now(),
        ] + $this->contact());
    }

    /**
     * GET /verify/{employee}/photo - only under a valid signature, and only for
     * somebody who works here now.
     */
    public function photo(Request $request, string $employee, DocumentStore $documents): StreamedResponse
    {
        abort_if(VerifySignature::check($employee, $request->query('signature'), $request->query('expires')) === null, 403);

        $record = VerifyDirectory::find($employee);
        abort_if($record === null, 404);

        $card = VerifyDirectory::card($record);
        $path = ($card['employee'] ?? null)?->profile?->photo_path;

        abort_if($card['state'] !== VerifyDirectory::WORKING || $path === null || ! $documents->exists($path), 404);

        return $documents->stream($path);
    }

    /**
     * GET /registration/{registration} - one registration's document, if it
     * has one. Public, like the number beside it.
     */
    public function registration(CompanyRegistration $registration): StreamedResponse
    {
        $path = $registration->document_path;

        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response(
            $path,
            str($registration->type)->slug().'.pdf',
            ['Content-Type' => 'application/pdf'],
            'inline',
        );
    }

    /**
     * The enquiry buttons and the registration IDs, from the dashboard.
     *
     * @return array<string, mixed>
     */
    protected function contact(): array
    {
        return [
            'phone' => VerifyDirectory::setting(VerifyDirectory::PHONE),
            'email' => VerifyDirectory::setting(VerifyDirectory::EMAIL),
            'registrations' => CompanyRegistration::orderBy('sort_order')->orderBy('id')->get(),
        ];
    }
}
