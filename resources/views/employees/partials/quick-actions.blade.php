@php
    $actions = [
        ['label' => 'Add Employee',    'icon' => 'employees',   'tone' => '',            'route' => 'employees.create'],
        ['label' => 'Assign Role',     'icon' => 'settings',    'tone' => 'tone-warn',   'route' => 'employees.index'],
        ['label' => 'Mark Attendance', 'icon' => 'attendance',  'tone' => 'tone-accent', 'route' => 'attendance.index'],
        ['label' => 'Run Payroll',     'icon' => 'salary',      'tone' => '',            'route' => 'salary.index'],
        ['label' => 'Leave Requests',  'icon' => 'leave',       'tone' => 'tone-alt',    'route' => 'leave.index'],
        ['label' => 'View Reports',    'icon' => 'reports',     'tone' => 'tone-danger', 'route' => 'reports.index'],
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
