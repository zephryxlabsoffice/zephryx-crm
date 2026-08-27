@php
    $actions = [
        ['label' => 'Add Project',   'icon' => 'projects',      'tone' => '',            'route' => 'projects.create'],
        ['label' => 'Create Task',   'icon' => 'tasks',         'tone' => 'tone-accent', 'route' => 'tasks.index'],
        ['label' => 'Submit EOD',    'icon' => 'announcements', 'tone' => 'tone-warn',   'route' => 'projects.updates'],
        ['label' => 'Assign Team',   'icon' => 'teams',         'tone' => '',            'route' => 'teams.index'],
        ['label' => 'Project Report','icon' => 'reports',       'tone' => 'tone-alt',    'route' => 'reports.index'],
        ['label' => 'View Meetings', 'icon' => 'meetings',      'tone' => 'tone-danger', 'route' => 'meetings.index'],
    ];
@endphp

<section class="rail-card">
    <div class="rail-hd">
        <strong>Quick Actions</strong>
    </div>

    <div class="qa-grid">
        @foreach ($actions as $action)
            <a class="qa-tile {{ $action['tone'] }}" href="{{ route($action['route']) }}">
                <span class="qa-ic" aria-hidden="true">
                    @include('partials.nav-icon', ['icon' => $action['icon']])
                </span>
                <span>{{ $action['label'] }}</span>
            </a>
        @endforeach
    </div>
</section>
