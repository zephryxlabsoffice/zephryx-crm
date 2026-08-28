@extends('layouts.app')

@php
    use App\Support\MeetingPresenter as P;
    $pill = P::status($meeting['status']);
    $timing = P::timing($meeting);
@endphp

@section('title', $meeting['title'])

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('meetings.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Meetings
            </a>
            <h1>{{ $meeting['title'] }}</h1>
            <p>{{ P::when($meeting) }}</p>
        </div>

        <div class="hd-actions">
            @if ($meeting['join_url'])
                <a class="btn {{ P::isJoinable($meeting) ? 'btn-primary' : 'btn-outline' }}"
                   href="{{ $meeting['join_url'] }}" target="_blank" rel="noopener noreferrer">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/>
                    </svg>
                    Join on Google Meet
                </a>
            @endif

            @if ($isOrganiser && $meeting['status'] === P::SCHEDULED)
                <form method="POST" action="{{ route('meetings.cancel', ['meeting' => $meeting['id']]) }}">
                    @csrf
                    <button class="btn btn-outline btn-danger" type="submit" disabled title="Cancelling is not built yet">
                        Cancel meeting
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if ($meeting['status'] === P::CANCELLED)
        @include('partials.notice', [
            'tone' => 'danger',
            'title' => 'This meeting was cancelled',
            'message' => ($meeting['cancel_reason'] ?: 'No reason was recorded.').' The Google invite has been withdrawn, so it is off everyone’s calendar.',
        ])
    @elseif ($meeting['status'] === P::REQUESTED)
        {{--
            The state that only exists because clients can ask but not create
            (decided 2026-08-28). Saying plainly that nothing has been sent
            matters: the alternative is a client sitting in a room that does not
            exist.
        --}}
        @include('partials.notice', [
            'tone' => 'warning',
            'title' => $meeting['requested_by'] ? $meeting['requested_by'].' asked for this meeting' : 'Not created yet',
            'message' => 'Nothing has gone to Google, so there is no calendar event, no invite and no link. A project manager or the system admin creates it.',
        ])
    @elseif (! $isAttendee)
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'You are not on this invite',
            'message' => 'The join link is shown only to attendees — a Meet link is effectively a password. Ask the organiser to add you if you should be there.',
        ])
    @endif

    <section class="mt-detail-grid">
        <div class="mt-detail-main">
            @include('meetings.partials.detail-card')
            @include('meetings.partials.attendees')
        </div>

        <aside class="rail">
            @include('meetings.partials.google')
        </aside>
    </section>
@endsection
