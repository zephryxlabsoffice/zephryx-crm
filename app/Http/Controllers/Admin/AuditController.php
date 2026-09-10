<?php

namespace App\Http\Controllers\Admin;

use App\Support\Admin\AuditDirectory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
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
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * IT PAGINATES IN SQL, WHICH THE FIXTURE DID NOT HAVE TO
 *
 * The demo source held nine rows and the controller sliced them in PHP. This is
 * the one table in the application with no upper bound on its size — every
 * sign-in, every module write, forever — so the page, the filter and the counts
 * are all queries. A version that loaded the log to show fifteen rows of it
 * would work for a year and then stop working suddenly.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class AuditController extends Controller
{
    protected const PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $kinds = AuditDirectory::kinds();

        $filters = $request->validate([
            'kind' => ['nullable', Rule::in(array_keys($kinds))],
        ]);

        return response()->view('admin.audit.index', [
            'activeNav' => 'audit',
            'entries' => AuditDirectory::paginate($filters['kind'] ?? null, self::PER_PAGE),
            'counts' => AuditDirectory::counts(),
            'kind' => $filters['kind'] ?? null,
            'kinds' => $kinds,
        ]);
    }

    public function show(Request $request, string $entry): Response
    {
        $record = AuditDirectory::find($entry);

        abort_if($record === null, 404);

        return response()->view('admin.audit.show', [
            'activeNav' => 'audit',
            'entry' => $record,
        ]);
    }
}
