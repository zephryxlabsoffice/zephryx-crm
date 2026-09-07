<?php

namespace App\Http\Controllers\Admin;

use App\Support\Demo\DemoMasterData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * The lookup lists the rest of the application reads.
 *
 * Nothing here deletes. These rows are referenced by records that already
 * exist, and removing one does not remove that history — it orphans it, leaving
 * an employee whose department is a blank cell that nobody can ever resolve.
 * The destructive act available is deactivation, and the row keeps answering
 * for the past. See the head of App\Support\Demo\DemoMasterData.
 *
 * `in_use` is counted from the records themselves rather than stored, so the
 * number the screen states before you deactivate something cannot disagree with
 * the module behind it.
 */
class MasterDataController extends Controller
{
    public function index(Request $request): Response
    {
        $lists = [];

        foreach (DemoMasterData::lists() as $key => $meta) {
            $rows = DemoMasterData::rows($key);

            $lists[$key] = $meta + [
                'key' => $key,
                'total' => $rows->count(),
                'active' => $rows->where('active', true)->count(),
                // A list with nothing in it is a module that cannot be used —
                // worth surfacing on the overview rather than discovering when
                // somebody tries to add an employee.
                'empty' => $rows->isEmpty(),
            ];
        }

        return response()->view('admin.master.index', [
            'activeNav' => 'master-data',
            'lists' => $lists,
        ]);
    }

    public function show(Request $request, string $list): Response
    {
        abort_unless(DemoMasterData::has($list), 404);

        $meta = DemoMasterData::lists()[$list];
        $rows = DemoMasterData::rows($list);

        return response()->view('admin.master.show', [
            'activeNav' => 'master-data',
            'list' => $list,
            'meta' => $meta,
            'rows' => $rows->sortByDesc('active')->values(),
            'inUse' => $rows->sum('in_use'),
        ]);
    }
}
