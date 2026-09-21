@extends('layouts.app')

@section('title', 'My Attendance')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            {{-- Reached from the roll, so it needs a way back — but only for
                 the people who could have come from there.
                 TODO (backend phase): gate on `attendance.view.all`. --}}
            @if ($canTrack)
                <a class="back-link" href="{{ route('attendance.index') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                    Attendance
                </a>
            @endif
            <h1>My Attendance</h1>
            <p>Your days, your hours, and what the record says about them.</p>
        </div>

        @if ($attends ?? true)
            <div class="hd-actions">
                <a class="btn btn-outline" href="{{ route('compoffs.mine') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                    My comp-offs
                </a>
            </div>
        @endif
        {{-- No check-in/out CTA here. Checking in and out is one button, it
             lives in one place — the card at the top of the rail — and
             duplicating it in the header would mean two controls for one
             act, one of which is always the wrong one to press. --}}
    </div>

    @include('attendance.partials.mine-kpis')

    <section class="att-grid">
        <div class="att-main">
            @include('attendance.partials.calendar')
            @include('attendance.partials.history')
        </div>

        <aside class="rail">
            @include('attendance.partials.today')

            @include('attendance.partials.summary', [
                'heading' => $month->format('F Y'),
                'total' => $summary['days_counted'],
                'totalLabel' => 'Days',
            ])

            @include('attendance.partials.policy')
        </aside>
    </section>
@endsection
