<?php

namespace App\Http\View\Composers;

use App\Models\User;
use App\Support\Navigation\Navigation;
use App\Support\NotificationDirectory;
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
            'notifications' => $this->notifications($realm, $user),
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
     * All three names come from the session now. The branches below are what is
     * left for a request with no account on it.
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
             * Only reachable signed out — a client with a session is named by
             * the branch above, from `users.name`, which for a portal account
             * holds the ORGANISATION's name (§2.2: a client account is a
             * company, and several people there may share it).
             *
             * This used to read the `?as=` development switch, which changed
             * whose portal was on screen and therefore had to change the corner
             * of the page with it. The switch is gone with the fixture it was
             * written for; what is left is the signed-out case, which names
             * nobody.
             */
            return ['name' => 'Signed out', 'role' => 'Client'];
        }

        if ($realm === Realm::ADMIN) {
            /*
             * One account, and it is not a person's — §2.1 makes the Admin
             * Panel a configuration surface operated by the owner, with no
             * personal records of its own. Naming an individual here would
             * imply it has some, and imply that a second one could be created.
             */
            return ['name' => config('zephryx.brand.name').' Admin', 'role' => 'Owner'];
        }

        return ['name' => 'Signed out', 'role' => 'Development preview'];
    }

    /**
     * The bell's contents — the signed-in person's own, newest first.
     *
     * Scoped to one reader by construction: NotificationDirectory has no
     * `all()`, only `bellFor($user)`, so there is no way to fill this panel
     * with somebody else's queue by forgetting a where clause.
     *
     * Kept as plain arrays because this is drawn on every page in the
     * application and the partial should not be able to lazy-load a relation
     * once per row.
     *
     * @return list<array<string, mixed>>
     */
    protected function notifications(string $realm, ?Authenticatable $user): array
    {
        /*
         * The bell is empty in the client portal, and that is a decision rather
         * than an omission.
         *
         * Every row Notifier writes is addressed to a staff account and worded
         * for one — "Rahul assigned you a task", "your leave was approved".
         * Handing a client the staff bell would leak the lot. There is no
         * client-addressed notification stream yet, and inventing one here
         * would mean deciding what a client gets told about their own
         * projects, which is a product question this module has not been asked.
         *
         * So it renders its own empty state until that stream exists.
         *
         * The account type is checked as well as the realm, and that is not
         * belt-and-braces: `$realm` is read from the PATH (see
         * Realm::forRequest) and is a drawing decision, never an authorisation
         * one. What decides whose bell this is has to come from the account.
         */
        if ($realm !== Realm::STAFF || ! $user instanceof User || $user->account_type !== Realm::STAFF) {
            return [];
        }

        return NotificationDirectory::bellFor($user);
    }
}
