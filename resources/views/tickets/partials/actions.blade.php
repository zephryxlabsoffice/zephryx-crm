{{--
    The action row every ticket list shares. `$current` suppresses the link to
    the page you are already on.
--}}
@php $current = $current ?? ''; @endphp

<div class="hd-actions">
    <a class="btn btn-primary" href="{{ route('tickets.create') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Create Ticket
    </a>

    @foreach ([
        'index' => ['All Tickets', 'tickets.index'],
        'mine' => ['My Tickets', 'tickets.mine'],
        'assigned' => ['Assigned to Me', 'tickets.assigned'],
        'projects' => ['My Project Tickets', 'tickets.projects'],
        'escalated' => ['Escalated', 'tickets.escalated'],
    ] as $key => [$label, $route])
        @continue($key === $current)
        <a class="btn btn-outline" href="{{ route($route) }}">{{ $label }}</a>
    @endforeach
</div>
