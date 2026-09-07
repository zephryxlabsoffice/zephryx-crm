<?php

namespace App\Http\Controllers\Auth;

use App\Support\Realm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * The page somebody lands on when their account is closed — an ex-employee, or
 * a client whose engagement has ended.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * IT IS REACHED ONLY AFTER THE PASSWORD IS RIGHT. THAT IS THE WHOLE DESIGN.
 *
 * Spec §4.2 step 4 says an inactive account gets the GENERIC failure at the
 * login form: "Invalid credentials", with no detail. That rule is not being
 * softened here, and this page is not a contradiction of it — it is what
 * happens one step later.
 *
 *   At the form, before the password is checked, saying "this account is
 *   closed" tells anybody who types an address whether it belongs to a real
 *   account and what state it is in. That is staff enumeration, and it is the
 *   exact leak §4.2 exists to prevent.
 *
 *   After the password is checked and found CORRECT, the person has proved they
 *   are the account holder. Telling them their own account is closed leaks
 *   nothing to anybody else, and withholding it means an ex-employee typing
 *   their correct password into "Invalid credentials" forever, concluding the
 *   system is broken and asking somebody to fix it.
 *
 * So the order is: authenticate first, THEN explain. Which is why this page is
 * not reachable by URL. It renders on a one-shot session value that only the
 * login flow can set, and nothing else. A page that anybody could open would
 * put the enumeration back — "does /account/inactive render for this address?"
 * is the same question in a different shape.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * WHAT THE BACKEND OWES
 *
 * 1. SET THE FLAG ONLY AFTER A SUCCESSFUL CREDENTIAL CHECK, never before, and
 *    never from anything the browser sends.
 *
 * 2. ISSUE NO SESSION. The person is not signed in and must not be: they have
 *    proved who they are, which is not the same as being let in. No realm
 *    cookie, no remember token, no trusted device.
 *
 * 3. THE FLAG IS ONE-SHOT. Flashed, so a reload does not keep the page alive
 *    and a shared machine does not show it to the next person.
 *
 * 4. LOG IT. A closed account whose password is still correct and still being
 *    typed is worth seeing in the audit log (§6) — it is either somebody who
 *    has not been told, or somebody using credentials they should no longer
 *    have.
 */
class AccountStatusController extends Controller
{
    /**
     * The flashed value the login flow sets: which kind of account it was.
     *
     * Deliberately not the account's id, email or name. Nothing about the
     * person needs to reach this page for it to say what it says, and the less
     * that is put in the session the less there is to leak out of it.
     */
    public const SESSION_KEY = 'account.inactive';

    /** @var list<string> */
    public const KINDS = [Realm::STAFF, Realm::CLIENT];

    /**
     * GET /account/inactive
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $kind = $request->session()->get(self::SESSION_KEY);

        /*
         * No flag, no page. Somebody typing the URL is sent to the login form
         * exactly as if they had asked for a page that is not there — which,
         * for them, it is not.
         */
        if (! in_array($kind, self::KINDS, true)) {
            return redirect()->route('login');
        }

        return $this->render($kind);
    }

    /**
     * The same page, rendered on demand, for reviewing it.
     *
     * Registered in local + debug only — see routes/web.php. This page is
     * almost impossible to see otherwise, which is how a page ships with a
     * broken layout or a sentence nobody read.
     */
    public function preview(string $kind): Response
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);

        return $this->render($kind);
    }

    protected function render(string $kind): Response
    {
        return response()->view('account.inactive', [
            'kind' => $kind,
            // The subject a reply does not have to begin by asking about. It
            // names the account type and nothing about the person.
            'subject' => config('zephryx.brand.name').' '.config('zephryx.brand.suffix')
                .' — '.($kind === Realm::CLIENT ? 'client account' : 'account').' closed',
        ]);
    }

    /**
     * The rule the login flow checks a kind against before flashing it, so an
     * unknown value can never reach the session in the first place.
     */
    public static function kindRule(): In
    {
        return Rule::in(self::KINDS);
    }
}
