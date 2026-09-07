<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Realm enforcement (foundation spec §3.1).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE FIRST BARRIER, AND THE ONE THAT DOES NOT DEPEND ON PERMISSIONS
 *
 * "Every request re-checks the session's realm server-side, in middleware,
 * before any data is read. A client session hitting /employees is refused. A
 * staff session hitting /admin is refused."
 *
 * Most of this file is that sentence, checked. The rest is about the guard
 * being STRUCTURAL — applied to a route group by the file a route lives in, so
 * that adding a page tomorrow cannot accidentally skip it.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class RealmGuardTest extends TestCase
{
    /* ══════════════════════════════════════════════════════════════════════
       CROSSING A REALM
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_client_session_cannot_reach_the_staff_realm(): void
    {
        $this->signInAsClient();

        foreach (['/dashboard', '/employees', '/salary', '/attendance', '/leave'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    public function test_a_staff_session_cannot_reach_the_client_portal(): void
    {
        $this->signInAsStaff();

        foreach (['/client/dashboard', '/client/invoices', '/client/projects'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    public function test_a_staff_session_cannot_reach_the_admin_panel(): void
    {
        /*
         * Including the CEO's. The Admin Panel is a separate account in a
         * separate realm with its own session cookie (§3) — it is not the top
         * of the staff hierarchy, and no amount of seniority reaches it.
         *
         * This is why Settings is not in the staff sidebar.
         */
        $this->signInAsStaff(['ceo', 'hr', 'manager']);

        foreach (['/admin/dashboard', '/admin/settings', '/admin/access', '/admin/audit'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    public function test_the_admin_account_cannot_reach_the_operational_realms(): void
    {
        /*
         * §2.1 in the strongest available form: the panel has no personal
         * records and no operational authority, so it is refused the staff and
         * client realms outright rather than merely lacking their permissions.
         */
        $this->signInAsAdmin();

        foreach (['/dashboard', '/salary', '/leave', '/client/dashboard'] as $url) {
            $this->get($url)->assertNotFound();
        }

        $this->get('/admin/dashboard')->assertOk();
    }

    public function test_the_wrong_realm_answers_not_found_rather_than_forbidden(): void
    {
        /*
         * "Forbidden" confirms the surface exists, which tells somebody probing
         * that there is an admin panel at that address and it is worth
         * attacking. Within a realm a 403 is right — it says "you are in the
         * right place and lack a permission" — but across one it is a
         * disclosure.
         */
        $this->signInAsStaff();

        $this->get('/admin/settings')->assertNotFound();
        $this->assertSame(404, $this->get('/admin/settings')->getStatusCode());
    }

    /* ══════════════════════════════════════════════════════════════════════
       NOT SIGNED IN
       ══════════════════════════════════════════════════════════════════════ */

    public function test_an_anonymous_request_is_sent_to_the_sign_in_form(): void
    {
        // Not a refusal: a state with an obvious remedy.
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/client/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }

    public function test_the_public_surfaces_stay_public(): void
    {
        // The landing page, the sign-in form and password reset are reachable
        // without a session, and must stay that way — a guard that swallowed
        // the login page would lock everybody out permanently.
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/forgot-password')->assertOk();
    }

    /* ══════════════════════════════════════════════════════════════════════
       A SESSION THAT SHOULD HAVE STOPPED WORKING
       ══════════════════════════════════════════════════════════════════════ */

    public function test_a_suspended_account_is_refused_even_with_a_live_session(): void
    {
        /*
         * §4.2 step 4 is about the sign-in form. This is the other half: an
         * account suspended AFTER signing in must stop working now, not when
         * its session happens to expire.
         *
         * Checked in the middleware rather than only at sign-in for exactly
         * that reason.
         */
        $user = $this->signInAsStaff();

        $this->get('/dashboard')->assertOk();

        $user->update(['status' => 'suspended']);

        $this->get('/dashboard')->assertNotFound();
    }

    public function test_a_suspended_account_holds_no_permissions_at_all(): void
    {
        // However many roles it has. The status check runs before roles are
        // read, so there is no combination that survives it.
        $user = $this->signInAsStaff(['ceo', 'hr'], status: 'suspended');

        $rbac = app(\App\Support\Rbac\Rbac::class);

        $this->assertSame([], $rbac->permissionsFor($user));
        $this->assertFalse($rbac->can($user, 'salary.view'));
        // Not even the Employee base, which is otherwise unconditional.
        $this->assertFalse($rbac->can($user, 'attendance.self'));
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE GUARD IS STRUCTURAL
       ══════════════════════════════════════════════════════════════════════ */

    public function test_every_realm_route_carries_its_guard(): void
    {
        /*
         * The property that matters more than any single URL: a route is inside
         * the guard because of the FILE it is written in, so this walks the
         * router rather than trusting a list.
         *
         * If somebody adds a staff page and it appears here without
         * `realm:staff`, they have written it in the wrong file — which is
         * exactly the mistake the split exists to make impossible.
         */
        $expected = [
            'client.' => 'realm:client',
            'admin.' => 'realm:admin',
        ];

        $unguarded = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null) {
                continue;
            }

            foreach ($expected as $prefix => $middleware) {
                if (str_starts_with($name, $prefix) && ! in_array($middleware, $route->gatherMiddleware(), true)) {
                    $unguarded[] = $name;
                }
            }
        }

        $this->assertSame([], $unguarded, 'realm routes without their guard');
    }

    public function test_the_staff_routes_are_guarded_too(): void
    {
        /*
         * Staff routes have no name prefix to filter on, so this checks the one
         * from every module instead — chosen to span the whole file, because
         * the risk is a section that fell outside the group rather than a
         * single route.
         */
        $this->signInAsClient();

        foreach ([
            'dashboard', 'clients.index', 'employees.index', 'teams.index',
            'projects.index', 'tasks.index', 'tickets.index', 'invoices.index',
            'salary.index', 'attendance.index', 'leave.index', 'meetings.index',
            'announcements.index', 'notifications.index', 'profile.show',
        ] as $name) {
            $this->get(route($name))->assertNotFound();
        }
    }

    public function test_the_development_sign_in_bypass_is_gone(): void
    {
        /*
         * It existed for one commit, while realm enforcement was in place and
         * §4's credential check was not. Now that signing in works, a route
         * that skips it must not survive anywhere — "it's only local" is one
         * APP_DEBUG on the wrong server away from being the security model.
         */
        $this->assertFalse(app('router')->has('dev.sign-in'));
        $this->get('/dev/sign-in/EMP002')->assertNotFound();
    }

    public function test_nothing_outside_a_realm_file_reads_data(): void
    {
        /*
         * The routes left in web.php are the public ones: landing, sign-in,
         * password reset, the closed-account page and the local-only previews.
         * Anything else appearing here would be a page reachable without a
         * session.
         */
        $public = [];

        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            $isRealmGuarded = (bool) array_filter(
                $middleware,
                fn ($m) => is_string($m) && str_starts_with($m, 'realm:'),
            );

            if (! $isRealmGuarded && $route->getName() !== null) {
                $public[] = $route->getName();
            }
        }

        sort($public);

        /*
         * The local-only preview routes (`dev.errors`, `dev.account`) are
         * absent because they are not registered outside local + debug, which
         * is itself worth the list saying.
         *
         * `storage.local*` is Laravel's own local-disk server, not ours.
         */
        $this->assertSame([
            'account.inactive',
            'landing',
            'login',
            'login.attempt',
            'login.resend',
            'login.verify',
            'login.verify.attempt',
            'logout',
            'password.forgot',
            'password.request',
            'password.reset',
            'password.reset.form',
            'storage.local',
            'storage.local.upload',
            'theme.store',
        ], $public);
    }
}
