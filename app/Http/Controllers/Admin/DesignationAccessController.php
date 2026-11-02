<?php

namespace App\Http\Controllers\Admin;

use App\Models\MasterDataItem;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Admin\AccessDirectory;
use App\Support\Audit\AuditLog;
use App\Support\Rbac\DesignationAccess;
use App\Support\Rbac\Rbac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * What a designation grants, set from the Admin Panel (owner, 2026-11-02).
 *
 * Saving is two steps, like a setting: the first names everybody who gains or
 * loses what, and only the second writes. The audit entry keeps the old settings
 * and the people it reached, so a change can be put back by hand. Each designation
 * is its own row, so undoing one touches nobody on another.
 */
class DesignationAccessController extends Controller
{
    /** Everybody holds Employee already, and a Mentor has no job to hold. */
    protected const NOT_FOR_DESIGNATIONS = ['employee', 'mentor'];

    public function __construct(protected AuditLog $audit, protected Rbac $rbac) {}

    public function show(string $designation): Response
    {
        $item = $this->find($designation);

        return response()->view('admin.master.access', $this->page($item, DesignationAccess::settingsOf($item->id)));
    }

    /**
     * POST: a read, like `settings.preview`, so nothing proposed ends up in a URL.
     */
    public function preview(Request $request, string $designation): Response|RedirectResponse
    {
        $item = $this->find($designation);
        $proposed = $this->proposed($request);

        if ($this->same(DesignationAccess::settingsOf($item->id), $proposed)) {
            return redirect()
                ->route('admin.master.access', $item->id)
                ->with('status', 'Nothing changed.')
                ->with('status_tone', 'info');
        }

        return response()->view('admin.master.access-preview', [
            'activeNav' => 'master-data',
            'item' => $item,
            'proposed' => $proposed,
            'changes' => DesignationAccess::changesFor($item->id, $proposed),
            'labels' => Permission::pluck('permission_name', 'permission_key'),
            'roleNames' => Role::pluck('role_name', 'role_key'),
        ]);
    }

    public function update(Request $request, string $designation): RedirectResponse
    {
        $item = $this->find($designation);
        $proposed = $this->proposed($request);
        $before = DesignationAccess::settingsOf($item->id);

        if ($this->same($before, $proposed)) {
            return redirect()
                ->route('admin.master.access', $item->id)
                ->with('status', 'Nothing changed.')
                ->with('status_tone', 'info');
        }

        $changes = DesignationAccess::changesFor($item->id, $proposed);

        DB::transaction(function () use ($item, $proposed) {
            DB::table('designation_roles')->where('designation_id', $item->id)->delete();
            DB::table('designation_permissions')->where('designation_id', $item->id)->delete();

            $roles = Role::whereIn('role_key', $proposed['roles'])->pluck('id');

            DB::table('designation_roles')->insert($roles->map(fn (int $id) => [
                'designation_id' => $item->id,
                'role_id' => $id,
            ])->all());

            $permissions = Permission::whereIn('permission_key', array_keys($proposed['exceptions']))
                ->pluck('id', 'permission_key');

            DB::table('designation_permissions')->insert($permissions->map(fn (int $id, string $key) => [
                'designation_id' => $item->id,
                'permission_id' => $id,
                'effect' => $proposed['exceptions'][$key],
            ])->values()->all());
        });

        $this->rbac->forget();

        $this->audit->record(
            action: AuditLog::PERMISSION_CHANGED,
            actor: $request->user(),
            entityType: 'designation',
            entityId: $item->code,
            before: $this->describe($before),
            after: $item->name.': '.$this->describe($proposed).'. '.$this->describeChanges($changes),
            request: $request,
        );

        return redirect()
            ->route('admin.master.access', $item->id)
            ->with('status', $item->name.' is saved. '.$this->describeChanges($changes))
            ->with('status_tone', 'success');
    }

    protected function find(string $designation): MasterDataItem
    {
        abort_unless(DesignationAccess::ready(), 404);

        return MasterDataItem::where('list', MasterDataItem::DESIGNATIONS)->findOrFail((int) $designation);
    }

    /**
     * @param  array{roles: list<string>, exceptions: array<string, string>}  $settings
     * @return array<string, mixed>
     */
    protected function page(MasterDataItem $item, array $settings): array
    {
        return [
            'activeNav' => 'master-data',
            'item' => $item,
            'settings' => $settings,
            'roles' => $this->offeredRoles(),
            'permissions' => AccessDirectory::permissions(),
            'sensitive' => AccessDirectory::sensitive(),
            'inherited' => DesignationAccess::grantsOf($settings['roles'], []),
            'holders' => DesignationAccess::holdersCount($item->id),
        ];
    }

    /**
     * @return Collection<int, Role>
     */
    protected function offeredRoles()
    {
        return Role::query()
            ->where('is_active', true)
            ->whereNotIn('role_key', self::NOT_FOR_DESIGNATIONS)
            ->orderBy('role_name')
            ->get();
    }

    /**
     * What was submitted, with Inherit dropped.
     *
     * The exceptions are read by hand because their keys contain dots, which the
     * validator would read as nesting.
     *
     * @return array{roles: list<string>, exceptions: array<string, string>}
     */
    protected function proposed(Request $request): array
    {
        $data = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*' => [Rule::in($this->offeredRoles()->pluck('role_key')->all())],
        ]);

        $submitted = $request->input('exceptions', []);
        $known = AccessDirectory::assignableKeys();
        $exceptions = [];

        foreach (is_array($submitted) ? $submitted : [] as $key => $effect) {
            if (! in_array($key, $known, true) || ! in_array($effect, ['inherit', DesignationAccess::ALLOW, DesignationAccess::NEVER], true)) {
                throw ValidationException::withMessages(['exceptions' => 'That is not an exception a designation can have.']);
            }

            if ($effect !== 'inherit') {
                $exceptions[$key] = $effect;
            }
        }

        return ['roles' => array_values(array_unique($data['roles'] ?? [])), 'exceptions' => $exceptions];
    }

    /**
     * @param  array{roles: list<string>, exceptions: array<string, string>}  $a
     * @param  array{roles: list<string>, exceptions: array<string, string>}  $b
     */
    protected function same(array $a, array $b): bool
    {
        sort($a['roles']);
        sort($b['roles']);
        ksort($a['exceptions']);
        ksort($b['exceptions']);

        return $a === $b;
    }

    /**
     * @param  array{roles: list<string>, exceptions: array<string, string>}  $settings
     */
    protected function describe(array $settings): string
    {
        $parts = [$settings['roles'] === [] ? 'no roles' : 'roles '.implode(', ', $settings['roles'])];

        foreach ($settings['exceptions'] as $key => $effect) {
            $parts[] = $effect.' '.$key;
        }

        return implode('; ', $parts);
    }

    /**
     * @param  list<array{name: string, gains: list<string>, loses: list<string>}>  $changes
     */
    protected function describeChanges(array $changes): string
    {
        $moved = array_filter($changes, fn (array $person) => $person['gains'] !== [] || $person['loses'] !== []);

        if ($moved === []) {
            return 'Nobody\'s access changed.';
        }

        return implode(' ', array_map(fn (array $person) => $person['name'].': '
            .($person['gains'] === [] ? '' : 'gained '.implode(', ', $person['gains']).' ')
            .($person['loses'] === [] ? '' : 'lost '.implode(', ', $person['loses']).' '), $moved));
    }
}
