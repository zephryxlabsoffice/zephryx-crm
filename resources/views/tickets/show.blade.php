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
            {{-- Both are writes with rules attached: resolving belongs to the
                 assignee or someone above them, escalating hands the ticket to
                 the review queue. Neither pretends to work yet. --}}
            <button class="btn btn-primary" type="button" disabled title="Resolving a ticket is not built yet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Mark Resolved
            </button>

            <button class="btn btn-outline" type="button" disabled title="Escalating a ticket is not built yet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 19V5"/><polyline points="5 12 12 5 19 12"/>
                </svg>
                Escalate
            </button>
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
