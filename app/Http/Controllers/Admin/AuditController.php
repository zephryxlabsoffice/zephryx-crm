<?php

namespace App\Http\Controllers\Admin;

use App\Support\Demo\DemoAudit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * The audit log (§6).
 *
 * Read only, and there is no write route in either direction — nothing posts to
 * it and nothing removes from it. That is not because the write is unbuilt: an
 * audit log with a delete button is not an audit log, and this panel is the
 * account whose actions most need the record.
 *
 * §6 requires actor, action, entity, before/after, IP, user agent and timestamp
 * on every entry, and the detail page shows all of them. Before and after
 * matter most: an entry recording that a value changed, without saying from
 * what to what, cannot answer the question somebody comes here with.
 */
class AuditController extends Controller
{
    protected const PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'kind' => ['nullable', Rule::in(array_keys(DemoAudit::kinds()))],
        ]);

        $entries = DemoAudit::ofKind($filters['kind'] ?? null);

        return response()->view('admin.audit.index', [
            'activeNav' => 'audit',
            'entries' => $this->paginate($entries, $request),
            'counts' => DemoAudit::counts(),
            'kind' => $filters['kind'] ?? null,
            'kinds' => DemoAudit::kinds(),
        ]);
    }

    public function show(Request $request, string $entry): Response
    {
        $record = DemoAudit::find($entry);

        abort_if($record === null, 404);

        return response()->view('admin.audit.show', [
            'activeNav' => 'audit',
            'entry' => $record,
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function paginate(Collection $rows, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            items: $rows->forPage($page, self::PER_PAGE)->values(),
            total: $rows->count(),
            perPage: self::PER_PAGE,
            currentPage: $page,
            options: ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
