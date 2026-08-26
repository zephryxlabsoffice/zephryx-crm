<?php

namespace App\Http\Controllers;

use App\Support\Demo\DemoClients;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Clients — the list staff work from (foundation spec §12).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FRONT END ONLY. There is no `clients` table yet; rows come from
 * App\Support\Demo\DemoClients, which returns nothing outside local + debug.
 *
 * Search, filtering and pagination are real and operate on whatever collection
 * they are handed, so replacing the source with an Eloquent query is a change
 * of one method — not a rewrite of the page.
 *
 * Still to add with the backend: the `clients.view` permission check, and the
 * ownership rule in §6 for the client realm's own view of these records.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ClientController extends Controller
{
    protected const PER_PAGE = 7;

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'pending', 'review', 'on_hold', 'completed'])],
        ]);

        $search = trim($filters['q'] ?? '');
        $status = $filters['status'] ?? null;

        $matches = DemoClients::all()
            ->when($search !== '', fn ($rows) => $rows->filter(
                fn (array $row) => str_contains(mb_strtolower($row['name'].' '.$row['industry'].' '.$row['project']), mb_strtolower($search))
            ))
            ->when($status, fn ($rows) => $rows->where('status', $status))
            ->values();

        return response()->view('clients.index', [
            'activeNav' => 'clients',
            'clients' => $this->paginate($matches, $request),
            'search' => $search,
            'status' => $status,
            'filtered' => $search !== '' || $status !== null,
            'stats' => DemoClients::stats(),
            'activity' => DemoClients::activity(),
            'meetings' => DemoClients::meetings(),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginate($rows, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            items: $rows->forPage($page, self::PER_PAGE)->values(),
            total: $rows->count(),
            perPage: self::PER_PAGE,
            currentPage: $page,
            options: [
                'path' => $request->url(),
                // Carries the search and filter across page links, so paging
                // through a filtered list does not silently reset it.
                'query' => $request->query(),
            ],
        );
    }
}
