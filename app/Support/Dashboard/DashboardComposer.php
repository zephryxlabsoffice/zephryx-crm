<?php

namespace App\Support\Dashboard;

/**
 * Turns config/dashboard.php plus a permission check into the page.
 *
 * The whole module is this: a registry, a filter, an order. Everything that
 * differs between an intern's dashboard and the CEO's is a `permission` key in
 * the configuration, which is why there is one dashboard rather than five (see
 * the head of config/dashboard.php for that argument).
 *
 * The filter takes a callable rather than a NavigationGate so the caller
 * decides what "allowed" means — the real gate in normal use, the real gate
 * intersected with a preview role while the RBAC engine does not exist yet.
 * See DashboardController::gate.
 */
class DashboardComposer
{
    /**
     * How many KPI tiles a row may hold.
     *
     * The registry lists eight, and somebody holding all eight gets a row that
     * wraps onto a second and third line of small numbers nobody reads. Four is
     * one clean row at every width the grid supports, and the tiles are ordered
     * so the four that survive are the four closest to the viewer's own work —
     * personal first, company-wide last.
     *
     * Everything trimmed here is still on the page: each tile summarises a
     * widget or a module that is a click away. A KPI row is a glance, not an
     * index.
     */
    public const MAX_KPIS = 4;

    /**
     * @param  callable(string): bool  $allows
     * @return list<array<string, mixed>>
     */
    public function kpis(callable $allows): array
    {
        return array_slice($this->visible(config('dashboard.kpis', []), $allows), 0, self::MAX_KPIS);
    }

    /**
     * One column's widgets, in weight order.
     *
     * @param  callable(string): bool  $allows
     * @return list<array<string, mixed>>
     */
    public function widgets(callable $allows, string $column): array
    {
        $inColumn = array_filter(
            (array) config('dashboard.widgets', []),
            fn (array $widget) => ($widget['column'] ?? 'main') === $column,
        );

        return $this->visible($inColumn, $allows);
    }

    /**
     * Every widget the viewer may see, across both columns.
     *
     * The controller hydrates from this list and nothing else, so a widget that
     * did not survive the filter never has its data assembled — the numbers
     * behind a card you cannot see are never read, let alone rendered into a
     * page and hidden with CSS.
     *
     * @param  callable(string): bool  $allows
     * @return list<array<string, mixed>>
     */
    public function all(callable $allows): array
    {
        return array_merge(
            $this->widgets($allows, 'main'),
            $this->widgets($allows, 'rail'),
        );
    }

    /**
     * @param  iterable<array<string, mixed>>  $entries
     * @param  callable(string): bool  $allows
     * @return list<array<string, mixed>>
     */
    protected function visible(iterable $entries, callable $allows): array
    {
        $kept = [];

        foreach ($entries as $entry) {
            /*
             * An entry with no permission key is a bug, not a public widget.
             * Failing closed here means forgetting the key hides the widget
             * during review rather than showing everyone's payroll after it.
             */
            if (($entry['permission'] ?? null) === null || ! $allows($entry['permission'])) {
                continue;
            }

            $kept[] = $entry;
        }

        usort($kept, fn (array $a, array $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

        return $kept;
    }
}
