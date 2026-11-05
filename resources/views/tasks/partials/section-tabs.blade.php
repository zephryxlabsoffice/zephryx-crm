{{--
    The Tasks section's own navigation. $sectionTab names the page being shown. A tab
    leading to a page somebody cannot open is left out rather than disabled.
--}}
@php
    $sectionUser = auth()->user();
    $hasDays = $sectionUser->hasEmployeeBase();
    $mayViewTasks = app(\App\Support\Rbac\Rbac::class)->can($sectionUser, 'tasks.view');
    $rbac = app(\App\Support\Rbac\Rbac::class);
    $receivesReports = $mayViewTasks && (
        $rbac->can($sectionUser, 'tasks.assign')
        || $rbac->can($sectionUser, \App\Models\EodReport::VIEW_ALL_PERMISSION)
        || ($rbac->can($sectionUser, \App\Support\EodUrgent::PROJECT_PERMISSION) && \App\Models\Project::whereIn('manager_id', \App\Models\Employee::where('user_id', $sectionUser->id)->select('id'))->exists())
        || \App\Models\Employee::whereIn('reports_to', \App\Models\Employee::where('user_id', $sectionUser->id)->select('id'))->exists()
        || \App\Models\Team::whereIn('lead_id', \App\Models\Employee::where('user_id', $sectionUser->id)->select('id'))->exists()
    );

    $eodIsLive = \App\Support\EodPolicy::isLive();

    $sectionTabs = array_filter([
        'eod' => $hasDays && $eodIsLive ? ['End of Day', route('tasks.eod')] : null,
        'mine' => $hasDays ? ['My Tasks', route('tasks.mine')] : null,
        'eod-team' => $receivesReports && $eodIsLive ? ['Team Reports', route('tasks.eod.team')] : null,
        'all' => $mayViewTasks ? ['All Tasks', route('tasks.index')] : null,
        'team' => $mayViewTasks ? ['Team Tasks', route('tasks.team')] : null,
    ]);
@endphp

<nav class="tabs tasks-tabs" aria-label="Tasks section">
    @foreach ($sectionTabs as $key => [$label, $href])
        <a class="tab @if ($sectionTab === $key) active @endif" href="{{ $href }}" @if ($sectionTab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
