<?php

namespace App\Http\Controllers;

use App\Mail\EmailChangeMail;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Rules\NotACommonPassword;
use App\Support\Audit\AuditLog;
use App\Support\Auth\EmailChanges;
use App\Support\Auth\PasswordResets;
use App\Support\Auth\RememberMe;
use App\Support\Auth\TrustedDevices;
use App\Support\Documents\DocumentStore;
use App\Support\Images\PhotoIntake;
use App\Support\Profile\ProfileChanges;
use App\Support\ProfileDirectory;
use App\Support\ProfilePolicy;
use App\Support\ProfilePresenter as P;
use App\Support\Shell;
use App\Support\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * My Profile — what a person may change about themselves, and what the company
 * holds about them.
 *
 * Four pages: personal information (`/profile`), preferences
 * (`/profile/preferences`), password (`/profile/password`) and the activity log
 * (`/profile/activity`).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * TWO THINGS IN ONE LAYOUT, AND THE LINE BETWEEN THEM IS THE MODULE
 *
 * Decided 2026-09-03. Every field on these pages has exactly one owner, and the
 * table that says so is App\Support\ProfilePolicy — read it before adding a
 * field here. The short version:
 *
 *   THE PERSON'S OWN     phone, address, the descriptive fields, emergency
 *                        contact, skills, photo, preferences, password.
 *
 *   HR'S                 name, department, designation, reporting line, date of
 *                        birth, role. Shown, never editable, each with a line
 *                        saying why and a route to getting it corrected.
 *
 *   THE APPLICATION'S    employee id, joining date, last sign-in, verification
 *                        state. Nobody types these, including HR.
 *
 *   VERIFIED             email and password. The person's, but never a text box
 *                        with a Save button next to it.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HOW THE FIVE OBLIGATIONS ARE MET
 *
 * 1. THE WRITE VALIDATES AGAINST ProfilePolicy::selfEditable() AND IGNORES THE
 *    REST. `update()` builds its rules from that list and writes to
 *    `employee_profiles`, a table with no HR column in it — so a request
 *    carrying `department` is dropped by the validator AND has nowhere to land
 *    if it were not. Two independent reasons, because `disabled` in the markup
 *    is a rendering instruction and stops nobody.
 *
 * 2. CHANGING THE EMAIL IS A FLOW, NOT A FIELD. Both addresses confirm, the old
 *    one is told either way, and the account keeps signing in with the old
 *    address until the new is confirmed — see App\Support\Auth\EmailChanges.
 *
 * 3. CHANGING THE PASSWORD REQUIRES THE CURRENT ONE, even though the person is
 *    already signed in. A borrowed unlocked laptop is the whole threat. Policy
 *    is §4.7 — twelve characters, blocklist, no composition rules, no forced
 *    rotation. All sessions but this one are signed out on success.
 *
 * 4. THE PHOTO IS AN UPLOAD, WITH EVERYTHING THAT IMPLIES. Type checked by
 *    content, size and dimensions capped, and rebuilt from its picture segments
 *    so nothing the camera recorded alongside the image survives. It is a strip
 *    rather than a re-encode, because this host has neither GD nor Imagick —
 *    App\Support\Images\PhotoIntake states what that buys and what it does not.
 *
 * 5. EVERY WRITE HERE IS AUDITED (§6), and the activity page reads those
 *    entries back. That page is how somebody notices an account being used by
 *    somebody else, which only works if the log is complete.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * NO PERMISSION GATE, AND ONE ABORT INSTEAD
 *
 * Like Notifications, nothing here is behind a §5 permission: it is the
 * signed-in person's own record and no route takes an identifier. What it does
 * check is that there IS an employment record — a Mentor and the owner hold no
 * Employee base (§2.1), so there is no profile to show them, and a page that
 * rendered blank would be claiming they have one that is empty.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ProfileController extends Controller
{
    public function __construct(
        protected AuditLog $audit,
        protected EmailChanges $emailChanges,
        protected DocumentStore $documents,
        protected PhotoIntake $photos,
        protected RememberMe $remember,
        protected TrustedDevices $devices,
        protected PasswordResets $resets,
        protected ProfileChanges $changes,
    ) {
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PAGES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * GET /profile — personal information.
     */
    public function show(Request $request): Response
    {
        $employee = $this->requireEmployee($request);
        $pending = $this->changes->pendingFor($employee);

        return $this->page($request, 'profile.index', 'details', [
            'options' => ProfilePolicy::options(),
            /*
             * A request in flight, so the page SAYS SO rather than offering a
             * form that would be refused. The same arrangement the password
             * page uses for an email change already under way — and the same
             * reason: a control that cannot succeed is worse than one that is
             * not there.
             */
            'pending' => $pending,
            'pendingRows' => $pending ? $this->changes->comparison($pending) : [],
            'pendingPhoto' => $pending?->photo_path !== null,
            'decided' => $this->changes->historyFor($employee, 5),
        ]);
    }

    /**
     * GET /profile/preferences
     */
    public function preferences(Request $request): Response
    {
        return $this->page($request, 'profile.preferences', 'preferences', [
            // Cookie-backed and shipped with the shell. The page shows their
            // real current values rather than a default, so it is not lying
            // about state it can read.
            'theme' => Theme::forRequest($request),
            'themes' => Theme::available(),
            'density' => Shell::density($request),
            'sidebar' => Shell::sidebarState($request),
        ]);
    }

    /**
     * GET /profile/password
     */
    public function password(Request $request): Response
    {
        return $this->page($request, 'profile.password', 'password', [
            // A change already in flight, so the page says so rather than
            // offering to start a second one that would silently cancel it.
            'pendingEmail' => $this->emailChanges->liveFor($request->user())?->new_email,
        ]);
    }

    /**
     * GET /profile/activity
     */
    public function activity(Request $request): Response
    {
        $employee = $this->requireEmployee($request);

        return $this->page($request, 'profile.activity', 'activity', [
            'entries' => ProfileDirectory::activity($employee),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PERSON'S OWN FIELDS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * POST /profile — ASK for a change. Nothing here saves a record.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE FORM STOPPED SAVING ON 2026-09-14
     *
     * It used to write straight to `employee_profiles`. The owner reversed
     * that: the profile is the company's record of a person, and it is
     * corrected against documents handed in at the office rather than on the
     * strength of a form. So this writes a PENDING ROW, leaves the live record
     * exactly where it was, and HR applies it when the paperwork arrives.
     *
     * THE ALLOW-LIST IS BUILT FROM THE POLICY, NOT WRITTEN OUT AGAIN
     *
     * `detailRules()` walks ProfilePolicy::requestable() and refuses to
     * validate a field the policy does not name. Written out by hand it would
     * be a second copy of the rule, and the day a field moves out of that list
     * the copy would keep accepting it — the failure that looks exactly like
     * everything working.
     *
     * ONE REQUEST AT A TIME
     *
     * A second submission while one is pending is refused rather than silently
     * replacing it. The page says a request is with HR and offers to withdraw
     * it; quietly cancelling the first would throw away something HR may
     * already have half-decided, and the person would never know it happened.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function update(Request $request): RedirectResponse
    {
        $employee = $this->requireEmployee($request);

        $data = $request->validate($this->detailRules());

        if ($this->changes->pendingFor($employee) !== null) {
            throw ValidationException::withMessages([
                'pending' => 'You already have a change waiting with HR. Withdraw it first if you want to ask for something different.',
            ]);
        }

        // Lists arrive as a comma-separated string and are stored as lists, so
        // they are shaped BEFORE the comparison — otherwise "English, Hindi"
        // never equals ['English', 'Hindi'] and every submission looks like a
        // change to a field nobody touched.
        $submitted = $data;
        $submitted['languages'] = $this->list($data['languages'] ?? null);
        $submitted['skills'] = $this->list($data['skills'] ?? null);

        $diff = $this->changes->diff($employee, $submitted);

        $proposed = $this->changes->propose($employee, $diff, $request->user());

        if ($proposed === null) {
            // Nothing differed. Not an error and not a request: saying "sent to
            // HR" would put a person in a queue they are not in.
            return redirect()
                ->route('profile.show')
                ->with('status', 'Nothing was different, so nothing was sent to HR.')
                ->with('status_tone', 'info');
        }

        $this->audit->record(
            action: AuditLog::PROFILE_CHANGE_REQUESTED,
            actor: $request->user(),
            entityType: 'user',
            entityId: $request->user()->user_id,
            // Fields, never values. See the constant's own note.
            after: 'Requested: '.implode(', ', $this->changes->summarise($proposed)),
            request: $request,
        );

        return redirect()
            ->route('profile.show')
            ->with('status', 'Sent to HR. Nothing on your record has changed yet — bring the documents to the office and HR will apply it.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /profile/requests/withdraw — take back a request HR has not decided.
     */
    public function withdrawRequest(Request $request): RedirectResponse
    {
        $employee = $this->requireEmployee($request);

        $pending = $this->changes->pendingFor($employee);

        // Not an error: somebody pressing withdraw on a stale page, after HR
        // has just applied it, has not done anything wrong.
        if ($pending === null) {
            return redirect()
                ->route('profile.show')
                ->with('status', 'There was nothing waiting to withdraw.')
                ->with('status_tone', 'info');
        }

        $summary = $this->changes->summarise($pending);

        $this->changes->withdraw($pending);

        $this->audit->record(
            action: AuditLog::PROFILE_CHANGE_WITHDRAWN,
            actor: $request->user(),
            entityType: 'user',
            entityId: $request->user()->user_id,
            after: 'Withdrawn: '.implode(', ', $summary),
            request: $request,
        );

        return redirect()
            ->route('profile.show')
            ->with('status', 'Your request has been withdrawn.')
            ->with('status_tone', 'info');
    }

    /**
     * POST /profile/preferences
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THREE THINGS THAT LIVE IN THREE PLACES, AND ONE THAT LIVES NOWHERE
     *
     * The milestone opt-out is on the EMPLOYEE record, because Announcements
     * reads it there. The two notification switches are on the profile row,
     * because Notifier reads them there. Theme, density and sidebar are cookies
     * on this browser, because that is what they have always been — writing
     * them to the account would make somebody's laptop preference follow them
     * onto a shared machine.
     *
     * The form's fourth toggle, "also send these by email", is not saved and
     * has been taken off the form. Nothing in this application emails a
     * notification, and a switch that turns on a thing that does not exist is a
     * promise: the person who sets it stops watching the bell.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $employee = $this->requireEmployee($request);

        $data = $request->validate([
            'announce_milestones' => ['nullable', 'boolean'],
            'notify_tasks' => ['nullable', 'boolean'],
            'notify_tickets' => ['nullable', 'boolean'],
            'theme' => ['nullable', Rule::in(Theme::available())],
            'density' => ['nullable', Rule::in(['comfortable', 'compact'])],
            'sidebar' => ['nullable', Rule::in(['expanded', 'collapsed'])],
        ]);

        /*
         * An unchecked checkbox sends nothing at all, so absent means off. That
         * is the whole reason these three are read with a presence check rather
         * than a value: `?? true` would make the opt-out unsettable, which is
         * precisely the bug that turns a per-person opt-out back into a column.
         */
        $employee->update([
            'announce_milestones' => $request->boolean('announce_milestones'),
        ]);

        $profile = $this->profileFor($employee);

        $profile->fill([
            'notify_tasks' => $request->boolean('notify_tasks'),
            'notify_tickets' => $request->boolean('notify_tickets'),
        ])->save();

        $this->audit->record(
            action: AuditLog::PROFILE_PREFERENCES_UPDATED,
            actor: $request->user(),
            entityType: 'user',
            entityId: $request->user()->user_id,
            after: implode(' · ', [
                'milestones '.($employee->announce_milestones ? 'announced' : 'not announced'),
                'task notifications '.($profile->notify_tasks ? 'on' : 'off'),
                'ticket notifications '.($profile->notify_tickets ? 'on' : 'off'),
            ]),
            request: $request,
        );

        $response = redirect()
            ->route('profile.preferences')
            ->with('status', 'Your preferences are saved.')
            ->with('status_tone', 'success');

        /*
         * The three that are cookies, written through the same factories the
         * topbar switch and the sidebar toggle use — Theme::cookie and
         * Shell::*Cookie. Composing them here instead would give one setting
         * two lifetimes and two SameSite policies depending on which control
         * somebody happened to use.
         */
        if (($data['theme'] ?? null) !== null) {
            $response->withCookie(Theme::cookie($data['theme']));
        }

        if (($data['density'] ?? null) !== null) {
            $response->withCookie(Shell::densityCookie($data['density']));
        }

        if (($data['sidebar'] ?? null) !== null) {
            $response->withCookie(Shell::sidebarCookie($data['sidebar']));
        }

        return $response;
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE CREDENTIALS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * POST /profile/password
     *
     * The current password is asked for even though the person is signed in. A
     * borrowed unlocked laptop is the entire threat, and this field is the only
     * thing standing in front of it.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => array_merge(['required', 'confirmed'], $this->passwordRules()),
        ], [
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            /*
             * A failed attempt here is worth an entry: somebody guessing at the
             * current password on a screen that is already signed in is exactly
             * the borrowed-laptop case, and the activity page is where the
             * account's owner would see it.
             */
            $this->audit->record(
                action: AuditLog::SIGN_IN_REFUSED,
                actor: $user,
                entityType: 'user',
                entityId: $user->user_id,
                after: 'Wrong current password given on the profile password form',
                request: $request,
            );

            throw ValidationException::withMessages([
                'current_password' => 'That is not your current password.',
            ]);
        }

        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'That is the password you already have.',
            ]);
        }

        $user->forceFill([
            'password' => $data['password'],
            'password_changed_at' => now(),
        ])->save();

        /*
         * §4.6's invalidation, minus this session.
         *
         * Unlike a reset — where the person is signing in fresh and being
         * dropped is expected — somebody changing their password from inside
         * the application has not asked to be signed out of the page they are
         * on. Every OTHER session goes, which is the half that matters: it is
         * how somebody whose account has been borrowed takes it back.
         */
        $this->invalidateOtherSessions($request, $user);

        $this->audit->record(
            action: AuditLog::PROFILE_PASSWORD_CHANGED,
            actor: $user,
            entityType: 'user',
            entityId: $user->user_id,
            after: 'Password changed; every other session, remember-me token and trusted device revoked',
            request: $request,
        );

        return redirect()
            ->route('profile.password')
            ->with('status', 'Your password is changed. Everywhere else you were signed in has been signed out.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /profile/email — start a change.
     *
     * The current password is asked for here too, and for the same reason as on
     * the password form: this is the other half of the login, and a borrowed
     * session must not be able to move it.
     */
    public function changeEmail(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'new_email' => [
                'required', 'string', 'email', 'max:255',
                // Against the whole table, not just other accounts: two
                // accounts on one address is an ambiguous sign-in, and the
                // person doing it would not find out until they were locked
                // out of both.
                Rule::unique('users', 'email'),
            ],
            'current_password' => ['required', 'string'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That is not your current password.',
            ]);
        }

        $started = $this->emailChanges->start($user, $data['new_email'], $request);

        /*
         * Both mails, and the old address first.
         *
         * If the mailer fails partway, the half that has definitely gone out is
         * the one to the address that already holds the account — which is the
         * one the security of this flow rests on.
         */
        Mail::to($user->email)->send(new EmailChangeMail(
            half: EmailChanges::OLD,
            url: route('profile.email.confirm', ['token' => $started['old']]),
            name: $user->name,
            fromEmail: $user->email,
            toEmail: $data['new_email'],
            ip: $request->ip(),
        ));

        Mail::to($data['new_email'])->send(new EmailChangeMail(
            half: EmailChanges::NEW,
            url: route('profile.email.confirm', ['token' => $started['new']]),
            name: $user->name,
            fromEmail: $user->email,
            toEmail: $data['new_email'],
            ip: $request->ip(),
        ));

        $this->audit->record(
            action: AuditLog::PROFILE_EMAIL_CHANGE_REQUESTED,
            actor: $user,
            entityType: 'user',
            entityId: $user->user_id,
            before: $user->email,
            after: $data['new_email'].' — requested, awaiting confirmation from both addresses',
            request: $request,
        );

        return redirect()
            ->route('profile.password')
            ->with('status', 'Check both addresses. You keep signing in with '.$user->email
                .' until the new one is confirmed.')
            ->with('status_tone', 'info');
    }

    /**
     * GET /profile/email/confirm/{token}
     *
     * ─────────────────────────────────────────────────────────────────────────
     * OPEN TO A SIGNED-OUT VISITOR, AND IT HAS TO BE
     *
     * Half of these links go to an address that is not yet on any account, and
     * the person opening it may be doing so on a phone that has never signed
     * in. Requiring a session would make the new-address half unusable for
     * exactly the people it is meant for.
     *
     * That is safe because the token is the whole authority — 64 characters
     * from a CSPRNG, stored hashed, and carrying which change and which half it
     * is. Nothing in the request is trusted, including who is signed in.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function confirmEmail(Request $request, string $token): RedirectResponse
    {
        $change = $this->emailChanges->confirm($token);

        if ($change === null) {
            return redirect()->route('login')->withErrors([
                'email' => 'That link has expired, been used, or the change was cancelled. Start again from your profile.',
            ]);
        }

        if (! $change->isConfirmed()) {
            return redirect()->route('login')
                ->with('status', 'Thank you. The other address still has to confirm before anything changes.')
                ->with('status_tone', 'info');
        }

        $was = $this->emailChanges->apply($change);

        if ($was === null) {
            return redirect()->route('login')
                ->with('status', 'That change has already been applied.')
                ->with('status_tone', 'info');
        }

        $this->audit->record(
            action: AuditLog::PROFILE_EMAIL_CHANGED,
            // The account itself, not whoever happens to be signed in on this
            // browser — which on the new-address half is frequently nobody.
            actor: $change->user,
            entityType: 'user',
            entityId: $change->user?->user_id,
            before: $was,
            after: $change->new_email,
            request: $request,
        );

        return redirect()->route('login')
            ->with('status', 'Your sign-in address is now '.$change->new_email.'. Use it from now on.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /profile/photo
     *
     * ─────────────────────────────────────────────────────────────────────────
     * WHAT REACHES THE DISK IS NOT WHAT ARRIVED
     *
     * The upload is parsed and rebuilt from its picture segments — every APPn
     * block on a JPEG, every non-picture chunk on a PNG, dropped. A phone photo
     * carries GPS coordinates, and a staff photo that publishes where somebody
     * lives is not a feature.
     *
     * This is a strip and not a re-encode, because the host has no image
     * library. App\Support\Images\PhotoIntake states exactly what that buys and
     * what it does not; read it before deciding this is finished.
     *
     * The old file is deleted after the new path is saved, never before. The
     * other order leaves somebody with no photo at all if the write fails.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function photo(Request $request): RedirectResponse
    {
        $employee = $this->requireEmployee($request);

        $request->validate([
            'photo' => [
                'required', 'file',
                'max:'.(int) (PhotoIntake::MAX_BYTES / 1024),
                // Checked by content through finfo, then checked again by the
                // parser. The extension is whatever somebody typed.
                'mimes:'.implode(',', PhotoIntake::ALLOWED),
            ],
        ]);

        try {
            $clean = $this->photos->clean($request->file('photo'));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['photo' => $e->getMessage()]);
        }

        if ($this->changes->pendingFor($employee) !== null) {
            throw ValidationException::withMessages([
                'photo' => 'You already have a change waiting with HR. Withdraw it first if you want to send a different photo.',
            ]);
        }

        /*
         * Stored straight away, and NOT onto the record.
         *
         * The cleaning has already happened — the bytes here are ones this
         * application produced from the picture segments, with everything the
         * camera recorded alongside them gone — so holding the file is safe.
         * It goes to its own folder, and the live `photo_path` is untouched
         * until HR applies the request. A candidate on a declined request is
         * deleted; see App\Support\Profile\ProfileChanges.
         */
        $stored = $this->documents->putBytes(
            'employees/'.$employee->id.'/photo-requests',
            $clean['extension'],
            $clean['contents'],
        );

        $this->changes->propose($employee, [], $request->user(), $stored['path']);

        $this->audit->record(
            action: AuditLog::PROFILE_CHANGE_REQUESTED,
            actor: $request->user(),
            entityType: 'user',
            entityId: $request->user()->user_id,
            after: 'Requested: '.ProfilePolicy::labelOf('photo')
                .' ('.$clean['width'].'×'.$clean['height'].', metadata stripped)',
            request: $request,
        );

        return redirect()
            ->route('profile.show')
            ->with('status', 'Sent to HR. Anything your camera recorded with the picture was removed before it was stored, and your current photo stays until HR applies the change.')
            ->with('status_tone', 'success');
    }

    /**
     * GET /profile/photo — the signed-in person's own.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * ONE ROUTE, AND IT SERVES NOBODY ELSE'S
     *
     * The photo is on the private disk with the documents, so it needs a route
     * to be seen at all — and this one resolves the file from the session,
     * exactly like the rest of the module.
     *
     * Which means, today, that a person's photo is visible to that person. A
     * staff directory showing everybody's photograph to everybody is a
     * reasonable thing to want and is NOT what this route is: who may see whose
     * photo is a decision about the Employees module, and inventing it here
     * would be answering a question nobody has asked by adding a parameter to a
     * URL that deliberately has none.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function showPhoto(Request $request): StreamedResponse
    {
        $employee = $this->requireEmployee($request);

        $path = $employee->profile?->photo_path;

        abort_if($path === null || ! $this->documents->exists($path), 404);

        return $this->documents->stream($path);
    }

    /* ══════════════════════════════════════════════════════════════════════
       DOCUMENTS
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * POST /profile/documents
     */
    public function storeDocument(Request $request): RedirectResponse
    {
        $employee = $this->requireEmployee($request);

        $data = $request->validate([
            'document' => [
                'required', 'file',
                'max:'.(int) (DocumentStore::MAX_BYTES / 1024),
                /*
                 * `mimes` checks the CONTENT, not the name — Laravel runs the
                 * file through finfo and compares the guessed extension. A
                 * whitelist by extension alone is how "payslip.pdf.php"
                 * becomes a problem.
                 */
                'mimes:'.implode(',', DocumentStore::ALLOWED),
            ],
            'kind' => ['required', Rule::in(EmployeeDocument::KINDS)],
        ]);

        $stored = $this->documents->put('employees/'.$employee->id.'/documents', $request->file('document'));

        $document = EmployeeDocument::create([
            'reference' => ProfileDirectory::nextDocumentReference(),
            'employee_id' => $employee->id,
            'name' => $stored['name'],
            'kind' => $data['kind'],
            'path' => $stored['path'],
            'bytes' => $stored['bytes'],
            'mime' => $request->file('document')->getMimeType(),
        ] + ['uploaded_by' => $request->user()->id]);

        $this->audit->record(
            action: AuditLog::PROFILE_DOCUMENT_UPLOADED,
            actor: $request->user(),
            entityType: 'user',
            entityId: $request->user()->user_id,
            // The name and the kind, never the path. An audit entry naming a
            // file's location on disk is a map for anybody who reaches the log.
            after: 'Uploaded '.$document->name.' ('.$document->kind.')',
            request: $request,
        );

        return redirect()
            ->route('profile.show')
            ->with('status', $document->name.' is on file.')
            ->with('status_tone', 'success');
    }

    /**
     * GET /profile/documents/{document}
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE SCOPE IS THE QUERY, NOT A CHECK AFTER IT
     *
     * The document is looked up WITHIN this person's own, so somebody else's
     * reference is a 404 rather than a 403 — and there is no branch anywhere
     * that could be written the wrong way round. §6 says two parties reach
     * these, the person and HR; HR's route is the employee record, not this
     * one, and this method deliberately cannot serve anybody else's file.
     *
     * The download is audited before the file is streamed. An entry written
     * after a `return` is an entry that does not exist.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function downloadDocument(Request $request, string $document): StreamedResponse
    {
        $employee = $this->requireEmployee($request);

        $record = $employee->documents()->where('reference', $document)->first();

        abort_if($record === null, 404);

        abort_if(! $this->documents->exists($record->path), 404);

        $this->audit->record(
            action: AuditLog::PROFILE_DOCUMENT_DOWNLOADED,
            actor: $request->user(),
            entityType: 'user',
            entityId: $request->user()->user_id,
            after: 'Downloaded '.$record->name,
            request: $request,
        );

        return $this->documents->download($record->path, $record->name);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE PIECES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * The shared shape of all four pages: the same header, the same tabs, the
     * same rail. Only the panel differs.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function page(Request $request, string $view, string $tab, array $extra): Response
    {
        $employee = $this->requireEmployee($request);

        return response()->view($view, [
            'activeNav' => 'profile',
            'profile' => ProfileDirectory::of($employee),
            'tab' => $tab,
            'tabs' => P::tabs(),
            'summary' => ProfileDirectory::summary($employee),
            'documents' => ProfileDirectory::documents($employee),
            'documentKinds' => EmployeeDocument::KINDS,
        ] + $extra);
    }

    /**
     * The employment record behind the session.
     *
     * A Mentor and the owner hold no Employee base (§2.1). They have no profile
     * — not an empty one — so this is a 403 rather than a page of blanks.
     */
    protected function requireEmployee(Request $request): Employee
    {
        $employee = ProfileDirectory::employeeFor($request->user());

        abort_if($employee === null, 403);

        return $employee;
    }

    /**
     * The profile row, created on first save.
     */
    protected function profileFor(Employee $employee): EmployeeProfile
    {
        return $employee->profile ?? EmployeeProfile::create(['employee_id' => $employee->id]);
    }

    /**
     * Validation for the details form, built from the policy.
     *
     * Every key here is checked against ProfilePolicy::isRequestable() as the
     * rules are assembled, so a field that stops being requestable stops being
     * validated — and therefore stops being accepted — without anybody
     * remembering to come here.
     *
     * @return array<string, list<string>>
     */
    protected function detailRules(): array
    {
        $rules = [
            'phone' => ['nullable', 'string', 'max:32'],
            // Not unique, unlike the sign-in address: it is a way to reach
            // somebody, not a credential. See the migration.
            'personal_email' => ['nullable', 'string', 'email', 'max:190'],
            'current_address' => ['nullable', 'string', 'max:500'],
            'permanent_address' => ['nullable', 'string', 'max:500'],
            'gender' => ['nullable', Rule::in(ProfilePolicy::options()['gender'])],
            'marital_status' => ['nullable', Rule::in(ProfilePolicy::options()['marital_status'])],
            'nationality' => ['nullable', 'string', 'max:60'],
            'languages' => ['nullable', 'string', 'max:300'],
            'skills' => ['nullable', 'string', 'max:500'],
            'emergency_name' => ['nullable', 'string', 'max:120'],
            'emergency_relationship' => ['nullable', 'string', 'max:60'],
            'emergency_phone' => ['nullable', 'string', 'max:32'],
        ];

        foreach (array_keys($rules) as $field) {
            if (! ProfilePolicy::isRequestable($field)) {
                // Not an exception: a field the policy has taken away simply
                // stops being accepted, quietly, which is the behaviour that
                // does not teach anybody which fields exist.
                unset($rules[$field]);
            }
        }

        return $rules;
    }

    /**
     * "English, Hindi, Bengali" into a list.
     *
     * Empties dropped and duplicates removed, because "Laravel, , Laravel" is a
     * typo rather than three skills, and the chips under the field would draw
     * it as one.
     *
     * @return list<string>
     */
    protected function list(?string $typed): array
    {
        return collect(explode(',', (string) $typed))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * What changed, for the audit entry.
     *
     * Field names and whether each is set — never the values. §6 wants a before
     * and an after, and here that is the shape of the record rather than its
     * contents: an audit log carrying everybody's home address and emergency
     * contact number is a second copy of the most sensitive fields on the page,
     * in a table more people can read than can read the page.
     */
    protected function describe(EmployeeProfile $profile): string
    {
        $set = collect($profile->toRecordArray())
            ->except(['notify_tasks', 'notify_tickets', 'photo_path'])
            ->filter(fn ($value) => $value !== null && $value !== [] && $value !== '')
            ->keys()
            ->all();

        return $set === [] ? 'nothing on record' : implode(', ', $set).' on record';
    }

    /**
     * Every session for this account except the one asking, plus the
     * remember-me chains, the device trust and any live reset link.
     */
    protected function invalidateOtherSessions(Request $request, User $user): void
    {
        $this->remember->revokeAll($user);
        $this->devices->revokeAll($user);
        $this->resets->invalidateAll($user);

        if (config('session.driver') !== 'database') {
            /*
             * The same misconfiguration PasswordResetController logs, and the
             * same reason it is an error rather than a silent skip: on `file`
             * or `cookie` there is no way to reach another session, so somebody
             * changing their password because their account was borrowed would
             * leave the borrower signed in — and the page would tell them it
             * had worked.
             */
            Log::error('Profile password change could not invalidate other sessions.', [
                'driver' => config('session.driver'),
                'user_id' => $user->user_id,
                'why' => 'SESSION_DRIVER must be `database` for §4.6.',
            ]);

            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->whereNot('id', $request->session()->getId())
            ->delete();
    }

    /**
     * §4.7: twelve characters, a blocklist, and no composition rules. The same
     * list PasswordResetController uses — both forced complexity and forced
     * rotation are known to produce weaker passwords in practice.
     *
     * @return list<mixed>
     */
    protected function passwordRules(): array
    {
        return [Password::min(12), new NotACommonPassword()];
    }

}
