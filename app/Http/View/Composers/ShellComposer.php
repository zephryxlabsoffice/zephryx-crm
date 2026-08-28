<?php

namespace App\Http\View\Composers;

use App\Support\Demo\DemoNotifications;
use App\Support\Navigation\Navigation;
use App\Support\Shell;
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

        $view->with([
            'navigation' => $this->navigation->for($user, $view->getData()['activeNav'] ?? null),
            'sidebarState' => Shell::sidebarState($this->request),
            'density' => Shell::density($this->request),
            'notifications' => $this->notifications(),
            'userName' => $user->name ?? 'Signed out',
            'userInitials' => Shell::initials($user->name ?? ''),
            'userRole' => $user->display_role ?? 'Development preview',
        ]);
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
    protected function notifications(): array
    {
        // TODO (backend phase): the signed-in user, not a fixed one. Until
        // authentication lands there is nobody to scope to, so the demo viewer
        // stands in — and returns nothing at all outside local + debug.
        return DemoNotifications::bellFor(DemoNotifications::VIEWER);
    }
}
