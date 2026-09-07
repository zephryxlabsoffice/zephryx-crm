<?php

namespace App\Http\Controllers;

use App\Support\Demo\DemoProfile;
use App\Support\ProfilePolicy;
use App\Support\ProfilePresenter as P;
use App\Support\Shell;
use App\Support\Theme;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

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
 * WHAT THE BACKEND OWES
 *
 * 1. THE WRITE VALIDATES AGAINST ProfilePolicy::selfEditable() AND IGNORES THE
 *    REST. `disabled` in the markup is a rendering instruction; it stops
 *    nobody. A request that arrives with `department` in it must have that key
 *    dropped, not saved, and not error — quietly ignoring an unexpected field
 *    is the behaviour that does not teach an attacker which ones exist.
 *
 * 2. CHANGING THE EMAIL IS A FLOW, NOT A FIELD. It is the login identifier
 *    (§4.1). The change is confirmed from the OLD address as well as the new
 *    one, the old address is notified either way, and the account keeps signing
 *    in with the old one until the new is verified. Anything less is an
 *    account-takeover primitive dressed as a profile form.
 *
 * 3. CHANGING THE PASSWORD REQUIRES THE CURRENT ONE, even though the person is
 *    already signed in. A borrowed unlocked laptop is the whole threat, and the
 *    current-password prompt is the only thing standing in front of it. Policy
 *    is §4.7 — twelve characters, blocklist (App\Rules\NotACommonPassword), no
 *    composition rules, no forced rotation. All sessions but this one are
 *    signed out on success.
 *
 * 4. THE PHOTO IS AN UPLOAD, WITH EVERYTHING THAT IMPLIES. Type checked by
 *    content and not by extension, re-encoded rather than stored as received,
 *    size capped, stripped of EXIF — a phone photo carries GPS coordinates, and
 *    a staff directory that publishes where everybody lives is not a feature.
 *
 * 5. EVERY WRITE HERE IS AUDITED (§6), and the person's own entries are what
 *    the activity page reads. That page is how somebody notices an account
 *    being used by somebody else, which only works if the log is complete.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ProfileController extends Controller
{
    /**
     * GET /profile — personal information.
     */
    public function show(): Response
    {
        return $this->page('profile.index', 'details', [
            'options' => ProfilePolicy::options(),
        ]);
    }

    /**
     * GET /profile/preferences
     */
    public function preferences(Request $request): Response
    {
        return $this->page('profile.preferences', 'preferences', [
            // These three already work — they are cookie-backed and shipped
            // with the shell. The page shows their real current values rather
            // than a default, so it is not lying about state it can read.
            'theme' => Theme::forRequest($request),
            'themes' => Theme::available(),
            'density' => Shell::density($request),
            'sidebar' => Shell::sidebarState($request),
        ]);
    }

    /**
     * GET /profile/password
     */
    public function password(): Response
    {
        return $this->page('profile.password', 'password', []);
    }

    /**
     * GET /profile/activity
     */
    public function activity(): Response
    {
        return $this->page('profile.activity', 'activity', [
            'entries' => DemoProfile::activity(),
        ]);
    }

    /**
     * The shared shape of all four pages: the same header, the same tabs, the
     * same rail. Only the panel differs.
     *
     * @param  array<string, mixed>  $extra
     */
    protected function page(string $view, string $tab, array $extra): Response
    {
        $profile = DemoProfile::find();

        return response()->view($view, [
            'activeNav' => 'profile',
            'profile' => $profile,
            'tab' => $tab,
            'tabs' => P::tabs(),
            'summary' => DemoProfile::summary(),
            'documents' => DemoProfile::documents(),
        ] + $extra);
    }
}
