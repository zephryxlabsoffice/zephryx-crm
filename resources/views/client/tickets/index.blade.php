@extends('layouts.app')

@php use App\Support\TicketPresenter as TP; @endphp

@section('title', 'Support Tickets')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Support tickets</h1>
            <p>Anything that needs attention on your projects, and where it has got to.</p>
        </div>

        <div class="hd-actions">
            <a class="btn btn-primary" href="{{ route('client.tickets.create') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Raise a ticket
            </a>
        </div>
    </div>


    <section class="kpi-row" aria-label="Ticket summary">
        @include('client.partials.stat', [
            'label' => 'Open',
            'value' => $stats['open'] + $stats['unassigned'],
            'sub' => 'Waiting to be picked up',
            'icon' => 'tickets',
            'tone' => ($stats['open'] + $stats['unassigned']) > 0 ? 'tone-warn' : 'tone-soft',
        ])

        @include('client.partials.stat', [
            'label' => 'In progress',
            'value' => $stats['in_progress'],
            'sub' => 'Being worked on',
            'icon' => 'tasks',
            'tone' => 'tone-accent',
        ])

        @include('client.partials.stat', [
            'label' => 'Resolved',
            'value' => $stats['resolved'],
            'sub' => 'Closed out',
            'icon' => 'reports',
            'tone' => 'tone-soft',
        ])

        @include('client.partials.stat', [
            'label' => 'All tickets',
            'value' => $stats['total'],
            'sub' => 'Raised by you',
            'icon' => 'clients',
            'tone' => 'tone-alt',
        ])
    </section>

    <section class="dash-grid">
        <div class="dash-main">
            <section class="card table-card">
                <div class="card-hd">
                    <span class="card-title">Your tickets</span>

                    <form class="table-tools" method="GET" action="{{ route('client.tickets.index') }}">
                        <label class="sr-only" for="tk-status">Status</label>
                        <select class="chip-btn" id="tk-status" name="status" data-auto-submit>
                            <option value="">All statuses</option>
                            @foreach (TP::statusOptions() as $option)
                                <option value="{{ $option }}" @selected($status === $option)>{{ TP::status($option)['label'] }}</option>
                            @endforeach
                        </select>

                        <label class="sr-only" for="tk-priority">Priority</label>
                        <select class="chip-btn" id="tk-priority" name="priority" data-auto-submit>
                            <option value="">All priorities</option>
                            @foreach (TP::priorityOptions() as $option)
                                <option value="{{ $option }}" @selected($priority === $option)>{{ TP::priority($option)['label'] }}</option>
                            @endforeach
                        </select>

                        <button class="chip-btn" type="submit">Filter</button>
                    </form>
                </div>

                @if ($tickets->isEmpty())
                    <div class="card-body">
                        <p class="rail-empty">
                            No tickets. Raise one and it reaches the team on your project.
                        </p>
                    </div>
                @else
                    <div class="card-body-table">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th scope="col">Ticket</th>
                                    <th scope="col">Priority</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tickets as $ticket)
                                    <tr>
                                        <td>
                                            <a class="row-link" href="{{ route('client.tickets.show', $ticket['id']) }}">
                                                <strong>{{ $ticket['subject'] }}</strong>
                                                <span class="dash-sub">{{ $ticket['id'] }}</span>
                                            </a>
                                        </td>
                                        <td class="cell-tight">
                                            <span class="priority priority-{{ $ticket['priority'] }}">{{ TP::priority($ticket['priority'])['label'] }}</span>
                                        </td>
                                        <td class="cell-tight">
                                            <span class="pill {{ TP::status($ticket['status'])['tone'] }}">{{ TP::status($ticket['status'])['label'] }}</span>
                                        </td>
                                        <td class="cell-tight">{{ TP::ago($ticket['updated_at']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @include('partials.pagination', ['paginator' => $tickets, 'unit' => 'tickets'])
                @endif
            </section>
        </div>

        <aside class="rail">
            @include('client.partials.help')
        </aside>
    </section>
@endsection
