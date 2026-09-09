{{--
    Quick actions. Links, not buttons, because each one goes somewhere — the
    handover drew <button> elements, which cannot be opened in a new tab or
    reached by a screen reader as navigation.

    A tile that leads straight to a 403 is worse than no tile, so each one that
    has a permission behind it is filtered out for somebody who does not hold it
    — currently Add Client, whose route is guarded by `clients.create`. The rest
    lead to pages already gated by the sidebar.
--}}
@php
    $actions = [
        ['label' => 'Add Client',       'icon' => 'clients',       'tone' => '',             'route' => 'clients.create',  'when' => $mayCreate],
        ['label' => 'Create Invoice',   'icon' => 'invoices',      'tone' => 'tone-warn',    'route' => 'invoices.index'],
        ['label' => 'Send Proposal',    'icon' => 'announcements', 'tone' => 'tone-accent',  'route' => 'clients.index'],
        ['label' => 'Create Ticket',    'icon' => 'tickets',       'tone' => 'tone-danger',  'route' => 'tickets.index'],
        ['label' => 'Schedule Meeting', 'icon' => 'meetings',      'tone' => '',             'route' => 'meetings.index'],
        ['label' => 'Add Document',     'icon' => 'projects',      'tone' => 'tone-alt',     'route' => 'projects.index'],
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
