@extends('layouts.app')

@php use App\Support\MeetingPresenter as MP; @endphp

@section('title', 'Meetings')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Meetings</h1>
            <p>Calls with the team, past and upcoming.</p>
        </div>

        <div class="hd-actions">
            <a class="btn btn-primary" href="{{ route('client.meetings.create') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Request a meeting
            </a>
        </div>
    </div>

    @include('client.partials.switcher')

    <section class="kpi-row" aria-label="Meeting summary">
        @include('client.partials.stat', [
            'label' => 'Upcoming',
            'value' => $stats['scheduled'],
            'sub' => 'In the calendar',
            'icon' => 'meetings',
            'tone' => 'tone-soft',
        ])

        @include('client.partials.stat', [
            'label' => 'Awaiting confirmation',
            'value' => $stats['requested'],
            'sub' => $stats['requested'] > 0 ? 'We are finding a time' : 'Nothing pending',
            'icon' => 'calendar',
            'tone' => $stats['requested'] > 0 ? 'tone-warn' : 'tone-soft',
        ])

        @include('client.partials.stat', [
            'label' => 'Held',
            'value' => $stats['ended'],
            'sub' => 'Completed calls',
            'icon' => 'reports',
            'tone' => 'tone-accent',
        ])
    </section>

    <section class="dash-grid">
        <div class="dash-main">
            <section class="card table-card">
                <div class="card-hd">
                    <span class="card-title">All meetings</span>
                </div>

                @if ($meetings->isEmpty())
                    <div class="card-body">
                        <p class="rail-empty">No meetings yet. Request one and we will find a time.</p>
                    </div>
                @else
                    <div class="card-body-table">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th scope="col">Meeting</th>
                                    <th scope="col">When</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Join</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($meetings as $meeting)
                                    @php
                                        $state = MP::statusOf($meeting);
                                        $status = MP::status($state);
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $meeting['title'] }}</strong>
                                            @if ($meeting['project'])
                                                <span class="dash-sub">{{ $meeting['project'] }}</span>
                                            @endif
                                        </td>
                                        <td class="cell-tight">
                                            @if ($state === MP::REQUESTED)
                                                {{-- A requested meeting has no time yet. Printing
                                                     the requested slot as though it were confirmed
                                                     is how somebody misses a call that was never
                                                     scheduled. --}}
                                                <span class="dash-quiet-meta">Not scheduled yet</span>
                                            @else
                                                <div class="due-cell">
                                                    <strong>{{ MP::date($meeting['starts_at']) }}</strong>
                                                    <span>{{ MP::timeRange($meeting) }}</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="cell-tight"><span class="pill {{ $status['tone'] }}">{{ $status['label'] }}</span></td>
                                        <td class="cell-tight">
                                            @if (MP::isJoinable($meeting))
                                                <span class="dash-link">Link in your calendar</span>
                                            @else
                                                <span class="dash-quiet-meta">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @include('partials.pagination', ['paginator' => $meetings, 'unit' => 'meetings'])
                @endif
            </section>
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Next meeting</strong>
                </div>

                @if ($next === null)
                    <p class="rail-empty">Nothing scheduled.</p>
                @else
                    <div class="dash-next">
                        <span class="dash-when">{{ MP::when($next) }}</span>
                        <strong>{{ $next['title'] }}</strong>
                        <span class="dash-quiet-meta">{{ MP::timeRange($next) }} · {{ MP::duration($next) }}</span>
                    </div>

                    {{--
                        The invitation lives in the client's own calendar, and so
                        does the joining link and their response to it.

                        There is no Accept or Decline here, and there is not
                        going to be: responses belong to Google Calendar, and a
                        second set of buttons would be a second source of truth
                        for the same fact — one that could disagree with the
                        organiser's own calendar.
                    --}}
                    <p class="dash-note">
                        The joining link and your invitation are in the calendar
                        invite we sent. Accepting or declining there is what the
                        team sees.
                    </p>
                @endif
            </section>

            @include('client.partials.help')
        </aside>
    </section>
@endsection
