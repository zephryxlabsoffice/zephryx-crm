@extends('layouts.app')

@php use App\Support\TicketPresenter as TP; @endphp

@section('title', $ticket['id'])

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('client.tickets.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Support tickets
            </a>
            <h1>{{ $ticket['subject'] }}</h1>
            <p>{{ $ticket['id'] }}</p>
        </div>
    </div>


    <section class="dash-grid">
        <div class="dash-main">
            <section class="card">
                <div class="card-body">
                    <p class="cl-ticket-body">{{ $ticket['description'] }}</p>
                </div>
            </section>

            {{--
                The conversation. See client.partials.thread for why this is not
                the staff partial — sharing the markup is fine, sharing the
                composer and its "Internal note" button was not.

                The comments themselves are filtered twice before they reach
                here: ClientPortal::ticketComments applies ownership, then
                pins the audience to AUDIENCE_CLIENT. There is no argument this
                page could pass to ask for the staff thread.
            --}}
            @include('client.partials.thread')

            @if ($ticket['status'] === 'closed')
                @include('partials.notice', [
                    'tone' => 'info',
                    'title' => 'This ticket is closed',
                    'message' => 'Raise a new ticket if this continues — our team can reference this one.',
                ])
            @else
                <section class="card">
                    <div class="card-hd">
                        <span class="card-title">Add a reply</span>
                    </div>

                    <div class="card-body">
                        {{-- A comment posted here is
                             client-visible by construction, because the client
                             wrote it. --}}
                        <form method="POST" action="{{ route('client.tickets.comment', $ticket['id']) }}">
                            @csrf

                            <div class="form-field">
                                <label class="form-field-lbl sr-only" for="ticket-reply">Your reply</label>
                                <textarea id="ticket-reply" name="body" rows="4"
                                          maxlength="5000" required
                                          placeholder="Add anything that would help — a page, a screenshot, what you expected to happen.">{{ old('body') }}</textarea>
                            </div>

                            <div class="form-actions">
                                <button class="btn btn-primary" type="submit">Post reply</button>
                            </div>
                        </form>
                    </div>
                </section>
            @endif
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Details</strong>
                    <span class="pill {{ TP::status($ticket['status'])['tone'] }}">{{ TP::status($ticket['status'])['label'] }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Priority</span>
                    <span class="stat-value">
                        <span class="priority priority-{{ $ticket['priority'] }}">{{ TP::priority($ticket['priority'])['label'] }}</span>
                    </span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Raised</span>
                    <span class="stat-value">{{ TP::date($ticket['created_at']) }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Last update</span>
                    <span class="stat-value">{{ TP::ago($ticket['updated_at']) }}</span>
                </div>

                {{--
                    No assignee, no department, no internal category.

                    Who inside ZephryxLabs is holding a ticket, and which team
                    it was routed to, is our business — naming an individual
                    invites the client to chase that person directly, around the
                    process that exists so nothing gets lost.
                --}}
            </section>

            @include('client.partials.help')
        </aside>
    </section>
@endsection
