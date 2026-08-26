{{--
    Quick actions. Links, not buttons, because each one goes somewhere — the
    handover drew <button> elements, which cannot be opened in a new tab or
    reached by a screen reader as navigation.

    Each will gain its own permission check when the RBAC engine lands; a tile
    that leads straight to a 403 is worse than no tile.
--}}
@php
    $actions = [
        ['label' => 'Add Client',       'icon' => 'clients',       'tone' => '',             'route' => 'clients.create'],
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
