<?php

namespace App\Http\View\Composers;

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
     * Unread notifications for the bell.
     *
     * Empty until the notifications module lands (foundation spec §8); the
     * panel renders its own empty state. Kept as an array of plain rows so the
     * view does not depend on a model that does not exist yet.
     *
     * @return list<array<string, mixed>>
     */
    protected function notifications(): array
    {
        return [];
    }
}
