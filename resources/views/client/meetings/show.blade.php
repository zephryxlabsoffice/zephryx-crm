@extends('layouts.app')

@php use App\Support\MeetingPresenter as MP; @endphp

@section('title', $meeting['title'])

{{--
    One meeting, from the client's side.

    No join button and no attendee list. The join link lives in the calendar
    invite (see the index rail's note, and Meeting::toRecordArray, which
    withholds `join_url` from anybody not on the invite — a client account
    never is one). The attendee list is staff — see decorate() on the
    controller for why `organiser_record` arrives here trimmed to a name
    before this file ever sees it.
--}}
@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('client.meetings.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Meetings
            </a>
            <h1>{{ $meeting['title'] }}</h1>
            <p>{{ MP::when($meeting) }}</p>
        </div>
    </div>

    @php
        $state = MP::statusOf($meeting);
        $status = MP::status($state);
    @endphp

    @if ($state === MP::CANCELLED)
        @include('partials.notice', [
            'tone' => 'danger',
            'title' => 'This meeting was cancelled',
            'message' => ($meeting['cancel_reason'] ?: 'No reason was recorded.').' The invite has been withdrawn, so it is off everyone’s calendar.',
        ])
    @elseif ($state === MP::REQUESTED)
        @include('partials.notice', [
            'tone' => 'warning',
            'title' => 'Not created yet',
            'message' => 'Nothing has gone to Google, so there is no calendar event, no invite and no link. A project manager or the system admin creates it.',
        ])
    @endif

    <section class="dash-grid">
        <div class="dash-main">
            <section class="card">
                <div class="card-hd">
                    <span class="card-title">Details</span>
                </div>

                <div class="card-body">
                    <dl class="field-grid">
                        <div class="lv-field">
                            <dt class="lv-field-lbl">Status</dt>
                            <dd><span class="pill {{ $status['tone'] }}">{{ $status['label'] }}</span></dd>
                        </div>

                        @if ($meeting['project'])
                            <div class="lv-field">
                                <dt class="lv-field-lbl">Project</dt>
                                <dd>{{ $meeting['project_record']['name'] ?? $meeting['project'] }}</dd>
                            </div>
                        @endif

                        @if ($meeting['organiser_record'])
                            <div class="lv-field">
                                <dt class="lv-field-lbl">Organiser</dt>
                                <dd>{{ $meeting['organiser_record']['name'] }}</dd>
                            </div>
                        @endif

                        @if ($meeting['agenda'])
                            <div class="lv-field">
                                <dt class="lv-field-lbl">Agenda</dt>
                                <dd>{{ $meeting['agenda'] }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if ($state === MP::SCHEDULED)
                        <p class="dash-note">
                            The joining link and your invitation are in the calendar invite we sent.
                            Accepting or declining there is what the team sees.
                        </p>
                    @endif
                </div>
            </section>

            @if (in_array($state, [MP::SCHEDULED, MP::REQUESTED], true))
                <section class="card">
                    <div class="section-hd">Cancel this meeting</div>

                    <div class="prose prose-quiet">
                        <p>
                            Tell us why, and we will withdraw the invite if one has already gone
                            out. This cannot be undone from here — ask us to reschedule if you
                            still need the call.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('client.meetings.cancel', ['meeting' => $meeting['id']]) }}">
                        @csrf

                        <div class="form-field">
                            <label class="form-field-lbl" for="cancel-reason">Reason</label>
                            <textarea id="cancel-reason" name="reason" rows="3" required minlength="5" maxlength="500"
                                      placeholder="e.g. No longer needed — we resolved this over email.">{{ old('reason') }}</textarea>
                            @error('reason')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <button class="btn btn-outline btn-danger" type="submit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                            Cancel meeting
                        </button>
                    </form>
                </section>
            @endif
        </div>

        <aside class="rail">
            @include('client.partials.help')
        </aside>
    </section>
@endsection
