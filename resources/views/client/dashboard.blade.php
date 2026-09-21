@extends('layouts.app')

@php
    use App\Support\MeetingPresenter as MP;
    use App\Support\Money;
    use App\Support\ProjectPresenter as PP;
    use App\Support\TicketPresenter as TP;
@endphp

@section('title', 'Dashboard')

@section('content')
    <div class="page-hd">
        <h1>Welcome back, {{ $client }}</h1>
        <p>Where your work with ZephryxLabs stands today.</p>
    </div>


    {{--
        Two actions, not four navigation cards.

        The handover opened this page with "My Projects — view your active and
        completed projects" and three siblings, each duplicating a sidebar entry
        a few inches to the left. These are the two things a client arrives to
        DO and cannot do from the sidebar in one click.
    --}}
    <section class="qa-grid cl-actions">
        <a class="qa-tile tone-accent" href="{{ route('client.tickets.create') }}">
            <span class="qa-ic" aria-hidden="true">@include('partials.nav-icon', ['icon' => 'tickets'])</span>
            <span>Raise a support ticket</span>
        </a>

        <a class="qa-tile" href="{{ route('client.meetings.create') }}">
            <span class="qa-ic" aria-hidden="true">@include('partials.nav-icon', ['icon' => 'meetings'])</span>
            <span>Request a meeting</span>
        </a>
    </section>

    {{--
        Four tiles, computed from the same collections the panels below list.
        The handover had five, one of which repeated another's subtitle
        verbatim, and its invoice figures contradicted its own table.

        No "Completed Tasks" tile: tasks are internal work items, and a lifetime
        count reads zero for a client whose project started this month.
    --}}
    {{--
        Open tickets, then upcoming meetings, then outstanding invoices, then
        active projects — the order the client portal decisions (Q4,
        2026-09-21) settled on, leading with the two things most likely to
        need a client's attention today rather than the slowest-moving one.
    --}}
    <section class="kpi-row" aria-label="Summary">
        @include('client.partials.stat', [
            'label' => 'Open tickets',
            'value' => $stats['tickets']['total'] - $stats['tickets']['resolved'],
            'sub' => $stats['tickets']['resolved'].' resolved',
            'icon' => 'tickets',
            'tone' => ($stats['tickets']['total'] - $stats['tickets']['resolved']) > 0 ? 'tone-warn' : 'tone-soft',
            'route' => 'client.tickets.index',
        ])

        @include('client.partials.stat', [
            'label' => 'Upcoming meetings',
            'value' => $stats['meetings']['scheduled'],
            'sub' => $stats['meetings']['requested'] > 0
                ? $stats['meetings']['requested'].' awaiting confirmation'
                : 'Nothing awaiting confirmation',
            'icon' => 'meetings',
            'tone' => 'tone-accent',
            'route' => 'client.meetings.index',
        ])

        {{-- A MoneyBag, not a number: a mixed-currency set has no single total
             without an exchange rate this system does not have. --}}
        @include('client.partials.stat', [
            'label' => 'Outstanding',
            'value' => $stats['invoices']['outstanding']->headline()['lead'],
            'sub' => $stats['invoices']['overdue'] > 0
                ? $stats['invoices']['overdue'].' past its due date'
                : 'Nothing overdue',
            'icon' => 'invoices',
            'tone' => $stats['invoices']['overdue'] > 0 ? 'tone-warn' : 'tone-soft',
            'route' => 'client.invoices.index',
        ])

        @include('client.partials.stat', [
            'label' => 'Active projects',
            'value' => $stats['projects']['active'],
            'sub' => $stats['projects']['completed'] > 0
                ? $stats['projects']['completed'].' delivered'
                : 'Under way now',
            'icon' => 'projects',
            'tone' => 'tone-soft',
            'route' => 'client.projects.index',
        ])
    </section>

    <section class="dash-grid">
        <div class="dash-main">

            {{-- The updates are the point of this portal. A client wants to know
                 what happened this week, and this is the only page in the
                 application that answers it in one place. --}}
            <section class="card">
                <div class="card-hd">
                    <span class="card-title">Latest updates</span>
                    <a class="card-link" href="{{ route('client.projects.index') }}">Your projects</a>
                </div>

                <div class="card-body">
                    @if ($updates->isEmpty())
                        <p class="rail-empty">No updates posted yet.</p>
                    @else
                        <ol class="cl-updates">
                            @foreach ($updates as $update)
                                <li class="cl-update">
                                    <div class="cl-update-hd">
                                        <a href="{{ route('client.projects.show', $update['project_record']['id']) }}">
                                            {{ $update['project_record']['name'] }}
                                        </a>
                                        <span>{{ $update['posted_at']->format('d M, g:i A') }}</span>
                                    </div>
                                    <strong>{{ $update['title'] }}</strong>
                                    <p>{{ $update['body'] }}</p>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </section>

            <section class="card table-card">
                <div class="card-hd">
                    <span class="card-title">Your projects</span>
                    <a class="card-link" href="{{ route('client.projects.index') }}">View all</a>
                </div>

                @if ($projects->isEmpty())
                    <div class="card-body"><p class="rail-empty">No projects yet.</p></div>
                @else
                    <div class="card-body-table">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th scope="col">Project</th>
                                    <th scope="col">Progress</th>
                                    <th scope="col">Deadline</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($projects as $project)
                                    @php $deadline = PP::deadline($project['deadline'], $project['status']); @endphp
                                    <tr>
                                        <td>
                                            <a class="row-link" href="{{ route('client.projects.show', $project['id']) }}">
                                                <strong>{{ $project['name'] }}</strong>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="progress-cell {{ PP::progressState($project['progress']) }}">
                                                <progress class="progress" value="{{ $project['progress'] }}" max="100"></progress>
                                                <span class="progress-pct">{{ $project['progress'] }}%</span>
                                            </div>
                                        </td>
                                        <td class="cell-tight">
                                            <span class="{{ $deadline['state'] }}">{{ $deadline['label'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="card table-card">
                <div class="card-hd">
                    <span class="card-title">Support tickets</span>
                    <a class="card-link" href="{{ route('client.tickets.index') }}">View all</a>
                </div>

                @if ($tickets->isEmpty())
                    <div class="card-body"><p class="rail-empty">You have not raised any tickets.</p></div>
                @else
                    <div class="card-body-table">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th scope="col">Ticket</th>
                                    <th scope="col">Priority</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tickets as $ticket)
                                    <tr>
                                        <td>
                                            <a class="row-link" href="{{ route('client.tickets.show', $ticket['id']) }}">
                                                <strong>{{ $ticket['subject'] }}</strong>
                                            </a>
                                        </td>
                                        <td class="cell-tight">
                                            <span class="priority priority-{{ $ticket['priority'] }}">{{ TP::priority($ticket['priority'])['label'] }}</span>
                                        </td>
                                        <td class="cell-tight">
                                            <span class="pill {{ TP::status($ticket['status'])['tone'] }}">{{ TP::status($ticket['status'])['label'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Next meeting</strong>
                    <a class="dash-link" href="{{ route('client.meetings.index') }}">All</a>
                </div>

                @if ($nextMeeting === null)
                    <p class="rail-empty">Nothing scheduled.</p>
                    <div class="dash-punch-action">
                        <a class="btn btn-outline" href="{{ route('client.meetings.create') }}">Request a meeting</a>
                    </div>
                @else
                    <div class="dash-next">
                        <span class="dash-when">{{ MP::when($nextMeeting) }}</span>
                        <strong>{{ $nextMeeting['title'] }}</strong>
                        <span class="dash-quiet-meta">{{ MP::timeRange($nextMeeting) }} · {{ MP::duration($nextMeeting) }}</span>
                    </div>

                    <div class="dash-punch-action">
                        <a class="btn btn-primary" href="{{ route('client.meetings.show', ['meeting' => $nextMeeting['id']]) }}">Meeting details</a>
                    </div>
                @endif
            </section>

            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Invoices</strong>
                    <a class="dash-link" href="{{ route('client.invoices.index') }}">All</a>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Outstanding</span>
                    <span class="stat-value">{{ $stats['invoices']['outstanding']->format() }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Paid to date</span>
                    <span class="stat-value">{{ $stats['invoices']['collected']->format() }}</span>
                </div>

                @if ($stats['invoices']['overdue'] > 0)
                    <p class="dash-note dash-note-warn">
                        {{ $stats['invoices']['overdue'] === 1 ? 'One invoice is' : $stats['invoices']['overdue'].' invoices are' }}
                        past the due date.
                    </p>
                @endif
            </section>

            @include('client.partials.help')
        </aside>
    </section>
@endsection
