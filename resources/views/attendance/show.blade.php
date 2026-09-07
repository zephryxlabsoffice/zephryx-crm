@extends('layouts.app')

@php
    use App\Support\AttendancePresenter as P;
    $meta = P::state($record['state']);
@endphp

@section('title', P::date($record['date']).' · '.$record['employee_record']['name'])

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ $own ? route('attendance.mine') : route('attendance.index', ['date' => $record['date']]) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                {{ $own ? 'My attendance' : 'Attendance' }}
            </a>
            <h1>{{ P::longDate($record['date']) }}</h1>
            <p>{{ $record['employee_record']['name'] }} · {{ $record['employee'] }}</p>
        </div>
    </div>

    {{-- The reason, at the top, in full — and which KIND of rejection it was.
         The two are not interchangeable: one was somebody's judgement and can
         be undone, the other is a missing timestamp and cannot. A rejection
         somebody has to hunt for is one they will come and ask about in
         person. --}}
    @if ($record['auto_rejected'])
        @include('partials.notice', [
            'tone' => 'danger',
            'title' => 'Not counted — no check-out was recorded',
            'message' => 'The day was opened at '.P::time($record['date'], $record['check_in'])
                .' and never closed. After '.(int) $policy['window'].' hours there is no honest way to say how long it ran, so it does not count'
                .($own ? '.' : ' towards '.$record['employee_record']['name'].'’s month.')
                .' Nobody can type the missing time in — a recorded time is never edited, and this one cannot be restored.',
        ])
    @elseif ($record['rejected_at'] !== null)
        @include('partials.notice', [
            'tone' => 'danger',
            'title' => 'This record was rejected'
                .($record['rejecter_record'] ? ' by '.$record['rejecter_record']['name'] : '')
                .' on '.P::date($record['rejected_at']),
            'message' => $record['rejection_reason'],
        ])
    @elseif ($record['open'])
        @include('partials.notice', [
            'tone' => 'warning',
            'title' => 'This day is still open',
            'message' => 'Checked in at '.P::time($record['date'], $record['check_in'])
                .' with no check-out yet. Left open past '.(int) $policy['window'].' hours the day stops counting, and it cannot be filled in afterwards.',
        ])
    @endif

    <section class="att-detail-grid">
        <div class="att-main">
            @include('attendance.partials.record-card')

            @if ($canReject || $canRestore)
                @include('attendance.partials.reject')
            @endif
        </div>

        <aside class="rail">
            @include('attendance.partials.person')
            @include('attendance.partials.policy')
        </aside>
    </section>
@endsection
