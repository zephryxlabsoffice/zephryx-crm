@extends('layouts.app')

@php use App\Support\AttendancePresenter as P; @endphp

@section('title', 'Attendance')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Attendance</h1>
            <p>Who was in, and what the record says. Nothing here waits on an approval.</p>
        </div>

        <div class="hd-actions">
            @if ($mayRoster)
                <a class="btn btn-outline" href="{{ route('attendance.roster') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    Sunday roster
                </a>
            @endif
            <a class="btn btn-outline" href="{{ route('attendance.mine') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                My attendance
            </a>
        </div>
    </div>

    @include('attendance.partials.day-notice', ['headcount' => $stats['headcount']])

    @include('attendance.partials.kpis')

    <section class="att-grid">
        @include('attendance.partials.table')

        <aside class="rail">
            @include('attendance.partials.open-records')

            @include('attendance.partials.summary', [
                'heading' => 'That day at a glance',
                'total' => $stats['headcount'],
                // One short word. The donut's centre is about 60px across and a
                // longer label runs under the ring.
                'totalLabel' => 'On roll',
            ])

            @include('attendance.partials.policy')
        </aside>
    </section>
@endsection
