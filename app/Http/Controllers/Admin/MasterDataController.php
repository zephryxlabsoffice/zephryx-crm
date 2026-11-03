<?php

namespace App\Http\Controllers\Admin;

use App\Models\Announcement;
use App\Models\Employee;
use App\Models\MasterDataItem;
use App\Models\Permission;
use App\Models\Ticket;
use App\Support\Admin\MasterDataDirectory;
use App\Support\Audit\AuditLog;
use App\Support\Rbac\DesignationAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The lookup lists the rest of the application reads.
 *
 * These rows are referenced by records that already exist, and removing one that
 * is still pointed at does not remove that history - it orphans it, leaving an
 * employee whose department is a blank cell that nobody can ever resolve. So
 * retiring keeps the row answering for the past, and the one delete is a merge,
 * which moves everything off the row first. See the head of
 * App\Support\Admin\MasterDataDirectory.
 *
 * `in_use` is counted from the records themselves rather than stored, so the
 * number the screen states before you retire something cannot disagree with the
 * module behind it.
 */
class MasterDataController extends Controller
{
    public function __construct(protected AuditLog $audit) {}

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
            'placing' => $list === MasterDataItem::DESIGNATIONS && MasterDataDirectory::canPlaceDesignations(),
            'mergeable' => MasterDataDirectory::canMerge($list),
            'accessible' => $list === MasterDataItem::DESIGNATIONS && DesignationAccess::ready(),
            'departments' => MasterDataItem::query()->inList(MasterDataItem::DEPARTMENTS)->active()->get(),
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
     * reactivated instead - the records that pointed at it keep pointing at it,
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
             * not across the retired rows either - a retired name colliding
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
            'code.alpha_num' => 'A code is letters and digits only - other records reference it.',
        ]);

        $code = mb_strtoupper($data['code']);
        $place = $this->requestedPlacement($request, $list);

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
            $retired->update(['is_active' => true, 'name' => $data['name']] + $place);

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

        $item = MasterDataItem::create($place + [
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
     * RETIRING DESTROYS NOTHING
     *
     * The row stops being offered for new records and keeps answering for old
     * ones. Removing a row is what a merge does, once nothing points at it -
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
                'item' => 'That is the last active row in this list. Add its replacement first - '
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

    /**
     * POST /admin/master-data/designations/place
     *
     * Moves the designation on the list and nobody off their record. People who
     * hold it keep their department until it is edited, and the status says how
     * many that leaves elsewhere.
     */
    public function place(Request $request): RedirectResponse
    {
        abort_unless(MasterDataDirectory::canPlaceDesignations(), 404);

        $data = $request->validate([
            'item' => ['required', Rule::exists('master_data_items', 'id')->where('list', MasterDataItem::DESIGNATIONS)],
            'department_id' => ['nullable', Rule::exists('master_data_items', 'id')
                ->where('list', MasterDataItem::DEPARTMENTS)->where('is_active', true)],
        ]);

        $item = MasterDataItem::with('department')->findOrFail($data['item']);
        $before = $item->department?->name ?? 'not placed';

        $item->update(['department_id' => $data['department_id'] ?? null]);

        $after = $item->fresh('department')->department?->name;

        $this->audit->record(
            action: AuditLog::MASTER_DATA_CHANGED,
            actor: $request->user(),
            entityType: 'master_data',
            entityId: MasterDataItem::DESIGNATIONS.'/'.$item->code,
            before: $before,
            after: $item->name.' placed under '.($after ?? 'no department'),
            request: $request,
        );

        $elsewhere = $after === null ? 0 : Employee::where('designation_id', $item->id)
            ->where(fn ($query) => $query->whereNull('department_id')->orWhere('department_id', '!=', $item->department_id))
            ->count();

        return redirect()
            ->route('admin.master.show', ['list' => MasterDataItem::DESIGNATIONS])
            ->with('status', $item->name.' is now under '.($after ?? 'no department').'.'
                .($elsewhere > 0 ? ' '.$elsewhere.' '.($elsewhere === 1 ? 'person holding it sits' : 'people holding it sit')
                    .' in another department. Their records were not changed.' : ''))
            ->with('status_tone', $elsewhere > 0 ? 'info' : 'success');
    }

    /**
     * GET /admin/master-data/{list}/merge?item=
     *
     * Names everybody who would move before anything does, because a merge cannot
     * be undone by a button: afterwards they look like any other holder of the row.
     */
    public function confirmMerge(Request $request, string $list): Response
    {
        $item = MasterDataItem::where('list', $list)->findOrFail((int) $request->query('item'));
        $targets = MasterDataItem::query()->inList($list)->active()->whereNot('id', $item->id)->get();

        return response()->view('admin.master.merge', [
            'activeNav' => 'master-data',
            'list' => $list,
            'meta' => MasterDataDirectory::lists()[$list],
            'item' => $item,
            'people' => $this->peopleOn($item)->pluck('user.name')->all(),
            'targets' => $targets,
            'accessChanges' => $this->accessChanges($item, $targets),
            'labels' => Permission::pluck('permission_name', 'permission_key'),
        ]);
    }

    /**
     * POST /admin/master-data/{list}/merge
     *
     * Everything that points at the row moves to the other one, then the row is
     * deleted (owner, 2026-11-03), so a duplicate does not stay on the list as
     * retired. The audit entry keeps what it would take to put it back by hand:
     * its name and code, who moved, and what a designation carried. People merged
     * into a placed designation also take its department, so a merge does not
     * leave them somewhere their job says they are not.
     */
    public function merge(Request $request, string $list): RedirectResponse
    {
        $data = $request->validate([
            'item' => ['required', Rule::exists('master_data_items', 'id')->where('list', $list)],
            'into' => ['required', 'different:item', Rule::exists('master_data_items', 'id')
                ->where('list', $list)->where('is_active', true)],
        ], [
            'into.required' => 'Choose what to merge this into.',
            'into.different' => 'A row cannot be merged into itself.',
        ]);

        $item = MasterDataItem::findOrFail($data['item']);
        $into = MasterDataItem::with('department')->findOrFail($data['into']);
        $names = $this->peopleOn($item)->pluck('user.name')->all();
        $carried = $this->carriedBy($item);

        DB::transaction(function () use ($list, $item, $into) {
            if ($list === MasterDataItem::DEPARTMENTS) {
                Employee::where('department_id', $item->id)->update(['department_id' => $into->id]);
                Announcement::where('audience_department_id', $item->id)->update(['audience_department_id' => $into->id]);
                Ticket::where('department', $item->name)->update(['department' => $into->name]);

                if (MasterDataDirectory::canPlaceDesignations()) {
                    MasterDataItem::where('department_id', $item->id)->update(['department_id' => $into->id]);
                }
            } else {
                $home = $into->department?->is_active ? ['department_id' => $into->department_id] : [];

                Employee::where('designation_id', $item->id)->update(['designation_id' => $into->id] + $home);
            }

            $item->delete();
        });

        $this->audit->record(
            action: AuditLog::MASTER_DATA_CHANGED,
            actor: $request->user(),
            entityType: 'master_data',
            entityId: $list.'/'.$item->code,
            before: $item->name,
            after: 'merged into '.$into->name.' and deleted; moved: '.($names === [] ? 'nobody' : implode(', ', $names)).$carried,
            request: $request,
        );

        return redirect()
            ->route('admin.master.show', ['list' => $list])
            ->with('status', $item->name.' is merged into '.$into->name.' and deleted. '
                .count($names).' '.(count($names) === 1 ? 'person' : 'people').' moved.')
            ->with('status_tone', 'success');
    }

    /**
     * What a designation grants, for the audit entry. Deleting it takes these rows
     * with it, so afterwards this is the only record of them.
     */
    protected function carriedBy(MasterDataItem $item): string
    {
        if ($item->list !== MasterDataItem::DESIGNATIONS || ! DesignationAccess::ready()) {
            return '';
        }

        $settings = DesignationAccess::settingsOf($item->id);
        $parts = [];

        if ($settings['roles'] !== []) {
            $parts[] = 'roles: '.implode(', ', $settings['roles']);
        }

        foreach ($settings['exceptions'] as $permission => $effect) {
            $parts[] = $effect.' '.$permission;
        }

        return $parts === [] ? '' : '; it carried '.implode('; ', $parts);
    }

    /**
     * Who would gain or lose access, per row this designation could merge into.
     * A designation carries access, so a merge can change what somebody may do.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, MasterDataItem>  $targets
     * @return array<string, list<array<string, mixed>>>
     */
    protected function accessChanges(MasterDataItem $item, $targets): array
    {
        if ($item->list !== MasterDataItem::DESIGNATIONS || ! DesignationAccess::ready()) {
            return [];
        }

        $people = Employee::with('user.roles')->where('designation_id', $item->id)->get();
        $changes = [];

        foreach ($targets as $target) {
            $moved = array_filter(
                DesignationAccess::changesForPeople($people, DesignationAccess::settingsOf($target->id)),
                fn (array $person) => $person['gains'] !== [] || $person['loses'] !== [],
            );

            if ($moved !== []) {
                $changes[$target->name] = array_values($moved);
            }
        }

        return $changes;
    }

    /**
     * A department chosen while adding a designation. Empty for any other list.
     *
     * @return array{department_id?: ?int}
     */
    protected function requestedPlacement(Request $request, string $list): array
    {
        if ($list !== MasterDataItem::DESIGNATIONS || ! MasterDataDirectory::canPlaceDesignations()) {
            return [];
        }

        $data = $request->validate([
            'department_id' => ['nullable', Rule::exists('master_data_items', 'id')
                ->where('list', MasterDataItem::DEPARTMENTS)->where('is_active', true)],
        ]);

        return ['department_id' => isset($data['department_id']) ? (int) $data['department_id'] : null];
    }

    /**
     * @return Collection<int, Employee>
     */
    protected function peopleOn(MasterDataItem $item): Collection
    {
        $column = $item->list === MasterDataItem::DEPARTMENTS ? 'department_id' : 'designation_id';

        return Employee::with('user')->where($column, $item->id)->get()->toBase();
    }
}
