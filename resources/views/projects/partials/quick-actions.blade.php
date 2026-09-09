{{-- A tile that leads straight to a 403 is worse than no tile, so the one with
     a permission behind it is filtered out for somebody who does not hold it.
     The rest lead to pages the sidebar has already gated. --}}
@php
    $actions = [
        ['label' => 'Add Project',   'icon' => 'projects',      'tone' => '',            'route' => 'projects.create', 'when' => $mayCreate ?? false],
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
        @foreach (array_filter($actions, fn ($a) => $a['when'] ?? true) as $action)
            <a class="qa-tile {{ $action['tone'] }}" href="{{ route($action['route']) }}">
                <span class="qa-ic" aria-hidden="true">
                    @include('partials.nav-icon', ['icon' => $action['icon']])
                </span>
                <span>{{ $action['label'] }}</span>
            </a>
        @endforeach
    </div>
</section>
