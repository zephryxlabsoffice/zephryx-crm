<?php

namespace App\Http\Controllers\Admin;

use App\Models\MasterDataItem;
use App\Support\Admin\MasterDataDirectory;
use App\Support\Audit\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The lookup lists the rest of the application reads.
 *
 * Nothing here deletes. These rows are referenced by records that already
 * exist, and removing one does not remove that history — it orphans it, leaving
 * an employee whose department is a blank cell that nobody can ever resolve.
 * The destructive act available is deactivation, and the row keeps answering
 * for the past. See the head of App\Support\Admin\MasterDataDirectory.
 *
 * `in_use` is counted from the records themselves rather than stored, so the
 * number the screen states before you retire something cannot disagree with the
 * module behind it.
 */
class MasterDataController extends Controller
{
    public function __construct(protected AuditLog $audit)
    {
    }

    public function index(Request $request): Response
    {
        return response()->view('admin.master.index', [
            'activeNav' => 'master-data',
            'lists' => MasterDataDirectory::overview(),
        ]);
    }

    public function show(Request $request, string $list): Response
    {
        abort_unless(MasterDataDirectory::has($list), 404);

        return response()->view('admin.master.show', [
            'activeNav' => 'master-data',
            'list' => $list,
            'meta' => MasterDataDirectory::lists()[$list],
            'rows' => MasterDataDirectory::rows($list),
            'inUse' => MasterDataDirectory::totalInUse($list),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════════════
       THE WRITES
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * POST /admin/master-data/{list}
     *
     * ─────────────────────────────────────────────────────────────────────────
     * A CODE IS UNIQUE WITHIN ITS LIST, NOT ACROSS THE TABLE
     *
     * The four lists share a table because they share a shape and a screen, but
     * they are four lists: "SUP" as a department and "SUP" as a document type
     * are unrelated, and refusing the second because of the first would be the
     * table's implementation leaking onto the screen.
     *
     * REACTIVATION IS WHAT ADDING A RETIRED NAME DOES
     *
     * Somebody retires Operations, then a year later adds it back. A second row
     * with the same code would give the list two Operations, one of which
     * quietly holds all the history. So an existing row with that code is
     * reactivated instead — the records that pointed at it keep pointing at it,
     * which is the outcome the person meant.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function store(Request $request, string $list): RedirectResponse
    {
        abort_unless(MasterDataDirectory::has($list), 404);

        $data = $request->validate([
            /*
             * Unique among the ACTIVE rows of this list, twice over.
             *
             * Not across the table, because the four lists are four lists. And
             * not across the retired rows either — a retired name colliding
             * here would refuse the request, and the request is exactly the one
             * that should bring that row back. See below.
             */
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('master_data_items', 'name')
                    ->where('list', $list)
                    ->where('is_active', true),
            ],
            'code' => [
                'required', 'string', 'max:16', 'alpha_num',
                Rule::unique('master_data_items', 'code')
                    ->where('list', $list)
                    ->where('is_active', true),
            ],
        ], [
            'name.unique' => 'That name is already in this list.',
            'code.unique' => 'That code is already in this list.',
            'code.alpha_num' => 'A code is letters and digits only — other records reference it.',
        ]);

        $code = mb_strtoupper($data['code']);

        /*
         * The reactivation case, and the reason the unique rules above are
         * scoped to active rows: an ACTIVE duplicate was refused by the
         * validator, so what reaches here is a retired row with this code.
         */
        $retired = MasterDataItem::where('list', $list)
            ->where('code', $code)
            ->where('is_active', false)
            ->first();

        if ($retired !== null) {
            $retired->update(['is_active' => true, 'name' => $data['name']]);

            $this->audit->record(
                action: AuditLog::MASTER_DATA_CHANGED,
                actor: $request->user(),
                entityType: 'master_data',
                entityId: $list.'/'.$code,
                before: 'retired',
                after: 'brought back as '.$data['name'].'; everything that pointed at it still does',
                request: $request,
            );

            return redirect()
                ->route('admin.master.show', ['list' => $list])
                ->with('status', $data['name'].' was retired and is active again. Nothing that used it was affected.')
                ->with('status_tone', 'success');
        }

        $item = MasterDataItem::create([
            'list' => $list,
            'name' => $data['name'],
            'code' => $code,
            'is_active' => true,
            // Appended. The seeded rows carry an order somebody chose; a new
            // one has no claim to a position in the middle of it.
            'sort_order' => (int) MasterDataItem::where('list', $list)->max('sort_order') + 1,
        ]);

        $this->audit->record(
            action: AuditLog::MASTER_DATA_CHANGED,
            actor: $request->user(),
            entityType: 'master_data',
            entityId: $list.'/'.$item->code,
            after: 'added '.$item->name,
            request: $request,
        );

        return redirect()
            ->route('admin.master.show', ['list' => $list])
            ->with('status', $item->name.' added.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /admin/master-data/{list}/deactivate
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE ONLY DESTRUCTIVE ACT, AND IT DESTROYS NOTHING
     *
     * The row stops being offered for new records and keeps answering for old
     * ones. There is no delete route, no soft-delete column and no cascade —
     * see the head of MasterDataDirectory.
     *
     * A list cannot be emptied of active rows this way. An employee form with
     * no departments to choose from creates nobody, and the person retiring the
     * last one is looking at a row, not at the list.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function deactivate(Request $request, string $list): RedirectResponse
    {
        abort_unless(MasterDataDirectory::has($list), 404);

        $data = $request->validate([
            'item' => ['required', Rule::exists('master_data_items', 'id')->where('list', $list)],
        ]);

        $item = MasterDataItem::findOrFail($data['item']);

        if (! $item->is_active) {
            return redirect()
                ->route('admin.master.show', ['list' => $list])
                ->with('status', $item->name.' was already retired.')
                ->with('status_tone', 'info');
        }

        $remaining = MasterDataItem::where('list', $list)->active()->whereNot('id', $item->id)->count();

        if ($remaining === 0) {
            throw ValidationException::withMessages([
                'item' => 'That is the last active row in this list. Add its replacement first — '
                    .'a form with nothing to choose from creates nothing.',
            ]);
        }

        $inUse = $item->inUse();

        $item->update(['is_active' => false]);

        $this->audit->record(
            action: AuditLog::MASTER_DATA_CHANGED,
            actor: $request->user(),
            entityType: 'master_data',
            entityId: $list.'/'.$item->code,
            before: 'active',
            // The count goes in the entry, because it is the fact that makes
            // this a decision: retiring a department three people are in is a
            // different act from retiring an empty one, and afterwards nobody
            // can tell which it was.
            after: 'retired '.$item->name.' ('.($inUse === null
                ? 'nothing counts records against this list'
                : $inUse.' records still point at it').')',
            request: $request,
        );

        return redirect()
            ->route('admin.master.show', ['list' => $list])
            ->with('status', $item->name.' is retired. It is no longer offered, and everything '
                .'that already uses it still reads correctly.')
            ->with('status_tone', 'info');
    }
}
