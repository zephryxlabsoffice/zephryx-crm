<?php

namespace App\Http\Controllers;

use App\Support\Dashboard\DashboardComposer;
use App\Support\Dashboard\DashboardData;
use App\Support\DashboardPresenter as P;
use App\Support\Demo\DemoEmployees;
use App\Support\Demo\DemoProfile;
use App\Support\Demo\DemoRoles;
use App\Support\Navigation\NavigationGate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * The dashboard — assembled from the other modules, which is why §12 builds it
 * last.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ONE PAGE. THE ROLES ARE CONFIGURATION.
 *
 * The full argument is at the head of config/dashboard.php; the short version
 * is that §2.4 makes roles ADDITIVE, so Manager + HR is a person who exists and
 * a fixed per-role layout has no answer for them. This controller therefore
 * knows nothing about HR, or Mentors, or the CEO. It filters a registry and
 * hydrates what survived.
 *
 * The consequence worth stating: adding a widget is a line in config and a
 * partial. It is never a change here, and it is never a new page.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * WHAT THE BACKEND OWES
 *
 * 1. THE GATE. `NavigationGate` is bound to PermissiveGate, which allows
 *    everything and throws in production. Until the RBAC engine (§5) replaces
 *    it, every widget is visible to everybody in development and the page is
 *    not safe to deploy — which is true of the whole staff realm right now
 *    (routes/web.php).
 *
 * 2. THE VIEWER. `viewer()` returns a fixed demo employee because there is no
 *    session yet. One line, one place, deliberately: the fourteen widgets
 *    behind it all take the viewer as an argument rather than reading a
 *    constant, so authentication lands here and nowhere else.
 *
 * 3. THE WIDGETS ARE NOT THE GUARD. Each one summarises a module whose own
 *    routes check for themselves (§3.1). Hiding a card is a courtesy; if it
 *    ever becomes the only thing standing between somebody and payroll, the
 *    permission on the underlying page is missing.
 */
class DashboardController extends Controller
{
    public function __construct(
        private DashboardComposer $composer,
        private NavigationGate $gate,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $viewer = $this->viewer($request);
        $preview = $this->preview($request);
        $allows = $this->gate($request, $preview);

        /*
         * Filter, THEN hydrate. `all()` is the list that survived the
         * permission check, and it is the only list DashboardData is asked
         * about — the figures behind a widget the viewer cannot see are never
         * read. See the head of DashboardData for why that is not the same as
         * rendering everything and hiding some of it.
         */
        $visible = $this->composer->all($allows);

        $data = [];

        foreach ($visible as $widget) {
            $data[$widget['key']] = DashboardData::widget($widget['key'], $viewer);
        }

        $kpis = [];

        foreach ($this->composer->kpis($allows) as $tile) {
            $resolved = DashboardData::kpi($tile['key'], $viewer);

            if ($resolved !== null) {
                $kpis[] = $resolved + ['icon' => $tile['icon'] ?? 'dashboard'];
            }
        }

        return response()->view('dashboard.index', [
            'activeNav' => 'dashboard',
            'greeting' => P::greeting(),
            'firstName' => P::firstName($this->viewerName($viewer)),
            'today' => P::today(),
            'kpis' => $kpis,
            'main' => $this->composer->widgets($allows, 'main'),
            'rail' => $this->composer->widgets($allows, 'rail'),
            'data' => $data,
            // Null in a deployed application: the switcher is a development
            // affordance and DemoRoles returns nothing outside local + debug.
            'preview' => $preview,
            'previewRoles' => DemoRoles::all(),
        ]);
    }

    /**
     * The permission check the whole page is filtered through.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE PREVIEW CAN ONLY EVER TAKE THINGS AWAY
     *
     * Note the `&&`. The real gate is asked first and the preview role can only
     * narrow its answer — never widen it. That ordering is the entire safety
     * argument for `?as=`: when the RBAC engine replaces PermissiveGate,
     * `?as=ceo` on an intern's session still renders an intern's dashboard,
     * because the intern's gate already said no.
     *
     * A preview that substituted for the gate instead of intersecting with it
     * would be a privilege-escalation query parameter, which is a sentence
     * nobody should be able to write about a CRM.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @param  array{label: string, note: string, permissions: list<string>}|null  $preview
     * @return callable(string): bool
     */
    protected function gate(Request $request, ?array $preview): callable
    {
        $user = $request->user();

        if ($preview === null) {
            return fn (string $permission) => $this->gate->allows($user, $permission);
        }

        return fn (string $permission) => $this->gate->allows($user, $permission)
            && in_array($permission, $preview['permissions'], true);
    }

    /**
     * The role being previewed, if any.
     *
     * Validated against the registry rather than trusted, so `?as=` cannot put
     * arbitrary text on the page — the switcher renders the role's label, and
     * an unvalidated one would be reflected input on an authenticated page.
     *
     * @return array{label: string, note: string, permissions: list<string>}|null
     */
    protected function preview(Request $request): ?array
    {
        if (! DemoRoles::enabled()) {
            return null;
        }

        $validated = $request->validate([
            'as' => ['nullable', 'string', Rule::in(DemoRoles::keys())],
        ]);

        $role = $validated['as'] ?? null;

        return $role === null ? null : DemoRoles::find($role) + ['key' => $role];
    }

    /**
     * The signed-in person.
     *
     * This method used to return a fixed demo employee with a TODO promising
     * that "every widget takes the viewer as an argument, so this method is the
     * only thing that changes when sessions land". Sessions landed, and it was
     * the only thing that changed.
     *
     * It falls back to the demo viewer when the account has no matching
     * employee record — a Mentor, or a staff account seeded outside the demo
     * directory — because the widgets read from demo sources that are keyed by
     * employee id. That fallback disappears with the demo data.
     */
    protected function viewer(Request $request): string
    {
        $id = $request->user()?->user_id;

        return $id !== null && DemoEmployees::all()->contains('user_id', $id)
            ? $id
            : DemoProfile::VIEWER;
    }

    /**
     * The name in the greeting.
     *
     * Falls back to a neutral greeting rather than an id: "Good morning,
     * EMP002" is worse than no name at all.
     */
    protected function viewerName(string $viewer): string
    {
        $employee = DemoEmployees::all()->firstWhere('user_id', $viewer);

        return $employee['name'] ?? '';
    }
}
