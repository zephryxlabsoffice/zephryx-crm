<?php

namespace App\Support\Navigation;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;

/**
 * Builds the sidebar for a viewer (foundation spec §5, §12).
 *
 * Two filters, in order:
 *   1. Permission — the viewer must hold the entry's key.
 *   2. Existence  — the entry's route must be registered.
 *
 * The second matters while the application is being built module by module:
 * config/navigation.php lists the whole roadmap, and an entry whose module has
 * not shipped is simply skipped rather than blowing up on route().
 */
class Navigation
{
    public function __construct(private NavigationGate $gate)
    {
    }

    /**
     * @return list<array{key: string, label: string, icon: string, url: string, active: bool}>
     */
    public function for(?Authenticatable $user, ?string $activeKey = null): array
    {
        $items = [];

        foreach ((array) config('navigation', []) as $item) {
            if (! $this->gate->allows($user, $item['permission'])) {
                continue;
            }

            if (! Route::has($item['route'])) {
                continue;
            }

            $items[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'icon' => $item['icon'],
                'url' => route($item['route']),
                'active' => $activeKey !== null && $activeKey === $item['key'],
            ];
        }

        return $items;
    }
}
