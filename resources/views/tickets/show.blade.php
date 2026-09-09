@extends('layouts.app')

@php
    use App\Support\Avatar;
    use App\Support\TicketPresenter as P;
    $statusPill = P::status($ticket['status']);
@endphp

@section('title', $ticket['id'])

@section('content')
    {{--
        One overview, not four. The handover drew separate pages for the raiser,
        the assignee, the reviewer and the escalation view; they differed only
        in which panels were on screen. Four templates of the same page drift
        apart, and the panel that drifts here is the one showing internal notes.
    --}}
    <div class="page-hd-row">
        <div class="detail-hd">
            <a class="hd-back" href="{{ route('tickets.index') }}">
                <span class="sr-only">Back to tickets</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </a>

            <div class="page-hd">
                <h1>Ticket {{ $ticket['id'] }}</h1>
                <p>{{ P::ago($ticket['updated_at']) }} · raised {{ P::date($ticket['created_at']) }}</p>
            </div>
        </div>

        <div class="hd-actions">
            {{--
                Both are the same write — a triage — with one field pre-filled,
                rather than two endpoints that could disagree about what
                resolving means. They are shown to whoever holds the triage
                permission; everybody else reads the ticket and replies to it.
            --}}
            @if ($mayTriage)
                @if (! in_array($ticket['status'], ['resolved', 'closed'], true))
                    <form method="POST" action="{{ route('tickets.triage', ['ticket' => $ticket['id']]) }}">
                        @csrf
                        <input type="hidden" name="status" value="resolved">
                        <input type="hidden" name="assignee_id" value="{{ $ticket['assignee_record']['employee_id'] ?? '' }}">
                        <button class="btn btn-primary" type="submit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Mark Resolved
                        </button>
                    </form>
                @endif

                @if ($ticket['status'] !== 'escalated')
                    <form method="POST" action="{{ route('tickets.triage', ['ticket' => $ticket['id']]) }}">
                        @csrf
                        <input type="hidden" name="status" value="escalated">
                        <input type="hidden" name="assignee_id" value="{{ $ticket['assignee_record']['employee_id'] ?? '' }}">
                        <button class="btn btn-outline" type="submit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 19V5"/><polyline points="5 12 12 5 19 12"/>
                            </svg>
                            Escalate
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    <section class="tkt-detail-grid">
        <div class="tkt-detail-main">
            @include('tickets.partials.header-card')

            @if ($needsTriage)
                @include('tickets.partials.triage')
            @endif

            @include('tickets.partials.information')

            @include('tickets.partials.thread')

            @include('tickets.partials.attachments')
        </div>

        <aside class="rail">
            @include('tickets.partials.rail')
        </aside>
    </section>
@endsection
