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
     * The navigation entry for a path that is deliberately not built yet.
     *
     * Leads and Calendar keep their entries and return 404 until v2 (§12).
     * Without this the 404 page would tell someone who clicked a link we chose
     * to show them that the page does not exist — which reads as a broken
     * application rather than a decision.
     *
     * @return array<string, mixed>|null
     */
    public static function deferredEntryFor(string $path): ?array
    {
        $path = '/'.trim($path, '/');

        foreach ((array) config('navigation', []) as $item) {
            if (empty($item['deferred']) || ! Route::has($item['route'])) {
                continue;
            }

            $entryPath = '/'.trim(parse_url(route($item['route'], [], false), PHP_URL_PATH) ?? '', '/');

            if ($entryPath === $path) {
                return $item;
            }
        }

        return null;
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
