<?php

namespace App\Support\Navigation;

use App\Support\Realm;
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
     * Leads, Calendar and Reports keep their entries and return 404 until v2
     * (§12). Without this the 404 page would tell someone who clicked a link we
     * chose to show them that the page does not exist — which reads as a broken
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
     * The configuration file a realm's sidebar is built from.
     *
     * Three files, not one list with a `realm` column: a shared list would put
     * `/employees` and `/salary` one mistyped key away from a client's sidebar,
     * and separate files cannot make that mistake because the staff entries are
     * not in the client file at all. See the head of config/navigation-client.php.
     *
     * An unknown realm gets the client list, which is the smallest of the
     * three. Failing towards less is the only sensible direction for a default
     * here — certainly not towards the admin one.
     */
    protected function configFor(string $realm): string
    {
        return match ($realm) {
            Realm::STAFF => 'navigation',
            Realm::ADMIN => 'navigation-admin',
            default => 'navigation-client',
        };
    }

    /**
     * @return list<array{key: string, label: string, icon: string, url: string, active: bool}>
     */
    public function for(?Authenticatable $user, ?string $activeKey = null, string $realm = Realm::STAFF): array
    {
        $items = [];

        foreach ((array) config($this->configFor($realm), []) as $item) {
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
