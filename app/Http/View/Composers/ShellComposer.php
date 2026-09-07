<?php

namespace App\Http\View\Composers;

use App\Support\Demo\DemoClientPortal;
use App\Support\Demo\DemoNotifications;
use App\Support\Navigation\Navigation;
use App\Support\Realm;
use App\Support\Shell;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Supplies everything the app shell needs.
 *
 * Bound to the layout rather than passed from each controller: eighteen
 * modules should not each have to remember to hand the sidebar its own
 * navigation.
 */
class ShellComposer
{
    public function __construct(
        private Request $request,
        private Navigation $navigation,
    ) {
    }

    public function compose(View $view): void
    {
        $user = $this->request->user();

        /*
         * Which sidebar to draw. Read from the path, which is right for a
         * drawing decision and must never become an authorisation one — see
         * Realm::forRequest for why the two are kept apart.
         */
        $realm = Realm::forRequest($this->request);

        $identity = $this->identity($realm, $user);

        $view->with([
            'navigation' => $this->navigation->for($user, $view->getData()['activeNav'] ?? null, $realm),
            'realm' => $realm,
            'sidebarState' => Shell::sidebarState($this->request),
            'density' => Shell::density($this->request),
            'notifications' => $this->notifications($realm),
            'userName' => $identity['name'],
            'userInitials' => Shell::initials($identity['name']),
            'userRole' => $identity['role'],
        ]);
    }

    /**
     * Who the topbar says is signed in.
     *
     * A client account is an organisation, not a person: the portal greets
     * "DGL International School", not a contact's name. That is not cosmetic —
     * the account belongs to the company we invoice, several people at the
     * client may share it, and naming an individual in the corner of every page
     * would imply a per-person account that does not exist.
     *
     * TODO (backend phase): the signed-in account, from the session.
     *
     * @return array{name: string, role: string}
     */
    protected function identity(string $realm, ?Authenticatable $user): array
    {
        if ($user !== null) {
            return [
                'name' => $user->name ?? 'Signed out',
                'role' => $user->display_role ?? 'Development preview',
            ];
        }

        if ($realm === Realm::CLIENT) {
            /*
             * The development client switcher changes whose portal is being
             * viewed, so the topbar has to follow it — a page listing
             * GreenLeaf's tickets under DGL's name in the corner is worse than
             * either alone, because it makes a correctly-scoped page look
             * broken and a broken one look correct.
             *
             * Validated through knows(), which is empty outside local + debug,
             * so `?as=` cannot put arbitrary text in the topbar and does
             * nothing at all in a deployed portal.
             */
            $previewed = $this->request->query('as');

            return [
                'name' => DemoClientPortal::knows(is_string($previewed) ? $previewed : null)
                    ? $previewed
                    : DemoClientPortal::viewer(),
                'role' => 'Client',
            ];
        }

        return ['name' => 'Signed out', 'role' => 'Development preview'];
    }

    /**
     * The bell's contents — the signed-in person's own, newest first.
     *
     * Scoped to one reader by construction: DemoNotifications has no `all()`,
     * only `bellFor($employee)`, so there is no way to fill this panel with
     * somebody else's queue by forgetting a where clause.
     *
     * Kept as plain arrays so the view does not depend on a model that does not
     * exist yet, and empty outside local + debug so a deployed shell shows its
     * own empty state rather than invented activity.
     *
     * @return list<array<string, mixed>>
     */
    protected function notifications(string $realm): array
    {
        /*
         * The bell is empty in the client portal, and that is a decision rather
         * than an omission.
         *
         * Every notification in DemoNotifications is addressed to an EMPLOYEE
         * and worded for one — "Rahul assigned you a task", "your leave was
         * approved". Handing a client the staff bell would leak the lot. There
         * is no client-addressed notification stream yet, and inventing one
         * here would mean deciding what a client gets told about their own
         * projects, which is a product question this module has not been asked.
         *
         * So it renders its own empty state until that stream exists, which is
         * what the panel already does outside local + debug.
         */
        if ($realm !== Realm::STAFF) {
            return [];
        }

        // TODO (backend phase): the signed-in user, not a fixed one. Until
        // authentication lands there is nobody to scope to, so the demo viewer
        // stands in — and returns nothing at all outside local + debug.
        return DemoNotifications::bellFor(DemoNotifications::VIEWER);
    }
}
