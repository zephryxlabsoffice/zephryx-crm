@php
    use App\Support\Avatar;
    use App\Support\TicketPresenter as P;
@endphp

<section class="rail-card">
    <div class="rail-hd">
        <strong>People</strong>
    </div>

    <div class="rail-list">
        <div class="rail-row">
            <div class="rail-ic tone-accent" aria-hidden="true">
                @include('partials.nav-icon', ['icon' => $ticket['client'] ? 'clients' : 'employees'])
            </div>
            <div class="rail-body">
                <strong>{{ $ticket['client'] ?? ($ticket['raiser_record']['name'] ?? 'Unknown') }}</strong>
                <span>Raised this ticket</span>
            </div>
        </div>

        @if ($ticket['assignee_record'])
            <div class="rail-row">
                <span class="avatar {{ Avatar::tint($ticket['assignee_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($ticket['assignee_record']['name']) }}</span>
                <div class="rail-body">
                    <strong>{{ $ticket['assignee_record']['name'] }}</strong>
                    <span>Handling it</span>
                </div>
            </div>
        @endif

        @if ($ticket['escalator_record'])
            <div class="rail-row">
                <span class="avatar {{ Avatar::tint($ticket['escalator_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($ticket['escalator_record']['name']) }}</span>
                <div class="rail-body">
                    <strong>{{ $ticket['escalator_record']['name'] }}</strong>
                    <span>Escalated it</span>
                </div>
            </div>
        @endif
    </div>
</section>

<section class="rail-card">
    <div class="rail-hd">
        <strong>Ticket Details</strong>
    </div>

    <div>
        <div class="stat-row">
            <span class="stat-label">Type</span>
            <span class="stat-value">{{ P::typeLabel($ticket['type']) }}</span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Raised</span>
            <span class="stat-value">{{ P::date($ticket['created_at']) }}</span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Last update</span>
            <span class="stat-value">{{ P::ago($ticket['updated_at']) }}</span>
        </div>
    </div>
</section>

@if ($ticket['type'] === 'client')
    {{-- A standing reminder on exactly the tickets where it matters. It is not
         a substitute for the server-side filter, but the person typing is the
         one who decides which button to press. --}}
    <section class="rail-card">
        @include('partials.notice', [
            'tone' => 'warning',
            'title' => 'A client reads this ticket',
            'message' => 'Replies are shown to '.$ticket['client'].'. Use an internal note for anything they should not read.',
        ])
    </section>
@endif
