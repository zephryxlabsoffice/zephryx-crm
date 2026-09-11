<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Support\Dashboard\DashboardComposer;
use App\Support\Dashboard\DashboardData;
use App\Support\DashboardPresenter as P;
use App\Support\Navigation\NavigationGate;
use App\Support\Rbac\Rbac;
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
 * THREE THINGS THAT HAVE SINCE LANDED, AND ONE THAT HAS NOT CHANGED
 *
 * 1. THE GATE is the RBAC engine (§5). It was bound to a permissive stub that
 *    allowed everything and threw in production; the binding moved and nothing
 *    on this page did, which is what the NavigationGate contract was for.
 *
 * 2. THE VIEWER is the session's employment record, and it may be null — a
 *    Mentor and the owner hold no Employee base (§2.1). The fourteen widgets
 *    all take it as an argument rather than reading a constant, so this stayed
 *    one line in one place.
 *
 * 3. THE FIGURES are queries now, not a fixture. Which makes the filter-then-
 *    hydrate order below cheaper as well as safer: a widget nobody can see
 *    costs no query either.
 *
 * 4. THE WIDGETS ARE STILL NOT THE GUARD. Each one summarises a module whose own
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
            'firstName' => P::firstName($this->viewerName($request)),
            'today' => P::today(),
            'kpis' => $kpis,
            'main' => $this->composer->widgets($allows, 'main'),
            'rail' => $this->composer->widgets($allows, 'rail'),
            'data' => $data,
            // Null in a deployed application: the switcher is a development
            // affordance and both of these are empty outside local + debug.
            'preview' => $preview,
            'previewRoles' => $this->previewRoles(),
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
     * ─────────────────────────────────────────────────────────────────────────
     * IT PREVIEWS THE REAL ROLES NOW
     *
     * The permission sets came from a fixture, so the preview showed what a
     * Manager was assumed to hold. It reads `roles` and `role_permissions`,
     * which is what the application actually enforces — a preview that could
     * disagree with the engine was a preview of nothing.
     *
     * Validated against the table rather than trusted, so `?as=` cannot put
     * arbitrary text on the page: the switcher renders the role's name, and an
     * unvalidated one would be reflected input on an authenticated page.
     *
     * Still local + debug only. Unlike the client portal's switch — which
     * changed WHOSE data was shown and is gone — this one can only narrow the
     * viewer's own permissions (see the `&&` in `gate()`), so it is a review
     * affordance rather than an authorisation surface. It stays out of a
     * deployed application anyway, because a control that does nothing there is
     * a control somebody will ask about.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @return array{key: string, label: string, note: string, permissions: list<string>}|null
     */
    protected function preview(Request $request): ?array
    {
        if (! (app()->environment('local') && config('app.debug'))) {
            return null;
        }

        $validated = $request->validate([
            'as' => ['nullable', 'string', Rule::exists('roles', 'role_key')],
        ]);

        $key = $validated['as'] ?? null;

        if ($key === null) {
            return null;
        }

        $role = Role::with('permissions')->where('role_key', $key)->first();

        if ($role === null) {
            return null;
        }

        return [
            'key' => $role->role_key,
            'label' => $role->role_name,
            'note' => (string) $role->description,
            'permissions' => array_values(array_unique(array_merge(
                $role->permissions->pluck('permission_key')->all(),
                $this->baseFor($role),
            ))),
        ];
    }

    /**
     * The realm base a holder of this role would also carry.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * A ROLE IS NOT THE WHOLE OF WHAT SOMEBODY HOLDS
     *
     * §5 grants the Employee base by ACCOUNT TYPE — `staff_kind` — precisely so
     * that no role edit can revoke it. Which means a preview built from
     * `role_permissions` alone shows something nobody is: an "Employee" with no
     * attendance, no leave and no payslips, because those keys are not on the
     * role and never were.
     *
     * So the base is added back — except for the one role that names an account
     * type which does not carry it. §2.1: "a Mentor is staff and has none of
     * it", and `Rbac::NO_EMPLOYEE_BASE_ROLE` is where that fact is written
     * down, next to the base it qualifies.
     *
     * This is the only place in the application where a role key stands in for
     * an account kind, and it is confined to a development preview: the real
     * check is `User::hasEmployeeBase()`, on the column, and nothing here
     * grants anybody anything — the preview can only narrow (see `gate()`).
     *
     * @return list<string>
     */
    protected function baseFor(Role $role): array
    {
        return $role->role_key === Rbac::NO_EMPLOYEE_BASE_ROLE ? [] : Rbac::EMPLOYEE_BASE;
    }

    /**
     * The roles the switcher offers. Empty outside local + debug.
     *
     * @return array<string, array{label: string, note: string}>
     */
    protected function previewRoles(): array
    {
        if (! (app()->environment('local') && config('app.debug'))) {
            return [];
        }

        return Role::query()
            ->orderBy('role_name')
            ->get()
            ->mapWithKeys(fn (Role $role) => [$role->role_key => [
                'label' => $role->role_name,
                'note' => (string) $role->description,
            ]])
            ->all();
    }

    /**
     * The signed-in person's employment record.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * NULL IS A REAL ANSWER, AND THE FALLBACK IS GONE
     *
     * This used to fall back to a fixed demo employee when the account had no
     * matching record, because the widgets read fixtures keyed by staff id.
     * That fallback showed one person's tasks, leave and pay to anybody without
     * a record — harmless against invented data and not something to leave
     * behind next to real payroll.
     *
     * A Mentor and the owner hold no Employee base (§2.1). Their personal
     * widgets now have no answer rather than somebody else's, which is what the
     * empty states on those cards are for.
     * ─────────────────────────────────────────────────────────────────────────
     */
    protected function viewer(Request $request): ?Employee
    {
        $user = $request->user();

        return $user === null
            ? null
            : Employee::with('user')->where('user_id', $user->id)->first();
    }

    /**
     * The name in the greeting.
     *
     * Read from the ACCOUNT rather than the employment record, so a Mentor —
     * who has no record — is still greeted by name. Falls back to a neutral
     * greeting rather than an id: "Good morning, EMP002" is worse than no name
     * at all.
     */
    protected function viewerName(Request $request): string
    {
        return (string) ($request->user()?->name ?? '');
    }
}
