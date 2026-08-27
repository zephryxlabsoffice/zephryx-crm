@php
    use App\Support\Avatar;
    use App\Support\TicketPresenter as P;
    $statusPill = P::status($ticket['status']);
@endphp

<div class="card">
    <div class="tkt-hd">
        <div class="tkt-hd-body">
            <span class="tkt-hd-ref">{{ $ticket['id'] }}</span>
            <h1>{{ $ticket['subject'] }}</h1>

            <div class="tkt-hd-meta">
                <span class="pill {{ $statusPill['tone'] }}">{{ $statusPill['label'] }}</span>
                @include('tickets.partials.type-badge', ['type' => $ticket['type']])

                <span class="meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                    </svg>
                    Raised {{ P::date($ticket['created_at']) }}, {{ P::time($ticket['created_at']) }}
                </span>

                <span class="meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                    Updated {{ P::ago($ticket['updated_at']) }}
                </span>
            </div>
        </div>

        <div class="tkt-hd-side">
            <span class="lbl">Assigned to</span>
            @if ($ticket['assignee_record'])
                <span class="tkt-person">
                    <span class="avatar {{ Avatar::tint($ticket['assignee_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($ticket['assignee_record']['name']) }}</span>
                    <span class="tkt-person-text">
                        <strong>{{ $ticket['assignee_record']['name'] }}</strong>
                        <span>{{ $ticket['assignee_record']['designation'] }}</span>
                    </span>
                </span>
            @else
                <span class="tkt-unassigned">Nobody yet</span>
            @endif
        </div>
    </div>
</div>
