@extends('layouts.app')

@section('title', 'Schedule a meeting')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('meetings.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Meetings
            </a>
            <h1>Schedule a meeting</h1>
            <p>Creates the Google Calendar event and sends the invites.</p>
        </div>
    </div>

    @include('partials.notice', [
        'tone' => 'info',
        'title' => 'This form does not submit yet',
        'message' => 'The fields and the validation shape are real; the Google Calendar call lands with the backend. Nothing typed here is stored, and no invite goes anywhere.',
    ])

    <form class="mt-form" method="POST" action="{{ route('meetings.store') }}">
        @csrf

        <section class="mt-form-grid">
            <div class="mt-form-main">
                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        What and when
                    </div>

                    <div class="form-grid">
                        <div class="form-field mt-form-wide">
                            <label class="form-field-lbl" for="mt-title">Title</label>
                            <input id="mt-title" name="title" type="text" placeholder="Sprint planning, invoice query…" disabled>
                            <span class="pay-hint">This is what everyone sees in their calendar.</span>
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="mt-date">Date</label>
                            <input id="mt-date" name="date" type="date" disabled>
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="mt-time">Start time</label>
                            <input id="mt-time" name="time" type="time" disabled>
                            {{-- Says which clock. Times are stored in UTC and
                                 shown in one zone, so what is typed here is
                                 unambiguous even when the client is abroad. --}}
                            <span class="pay-hint">{{ $zone }}. Stored as UTC so it cannot drift when clocks change.</span>
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="mt-duration">Length</label>
                            <select id="mt-duration" name="duration" disabled>
                                @foreach ([15, 30, 45, 60, 90] as $minutes)
                                    <option value="{{ $minutes }}" @selected($minutes === $defaultDuration)>{{ $minutes }} minutes</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="mt-project">Project <span class="mt-optional">(optional)</span></label>
                            <select id="mt-project" name="project" disabled>
                                <option value="">Internal — no project</option>
                                @foreach ($projects as $option)
                                    <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-field mt-form-wide">
                            <label class="form-field-lbl" for="mt-agenda">What it is about</label>
                            <textarea id="mt-agenda" name="agenda" rows="3" placeholder="A line is enough — it goes into the invite." disabled></textarea>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        Who is coming
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="mt-staff">Colleagues</label>
                            {{-- A multiple select, not a JavaScript token field:
                                 it works without a script, submits an array the
                                 backend can validate, and is operable from a
                                 keyboard by default. --}}
                            <select id="mt-staff" name="staff[]" multiple size="6" disabled>
                                @foreach ($employees as $person)
                                    <option value="{{ $person['id'] }}">{{ $person['name'] }} — {{ $person['detail'] }}</option>
                                @endforeach
                            </select>
                            <span class="pay-hint">Ctrl or Cmd to pick more than one.</span>
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="mt-clients">Clients <span class="mt-optional">(optional)</span></label>
                            <select id="mt-clients" name="clients[]" multiple size="6" disabled>
                                @foreach ($clients as $client)
                                    <option value="{{ $client }}">{{ $client }}</option>
                                @endforeach
                            </select>
                            {{-- Clients join from outside the Workspace, which
                                 is a setting on the Google event rather than
                                 something this form controls — recorded in
                                 config/meetings.php so it is not forgotten. --}}
                            <span class="pay-hint">They get the invite by email and can join without a Google account.</span>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="rail">
                <section class="rail-card">
                    <div class="rail-hd"><strong>What happens on save</strong></div>

                    <ol class="mt-steps">
                        <li>A Google Calendar event is created on the company account.</li>
                        <li>Google generates the Meet link — this application never invents one.</li>
                        <li>Invites go out; people accept or decline in their own calendar.</li>
                        <li>The link appears here for attendees only.</li>
                    </ol>

                    <div class="mt-submit">
                        <button class="btn btn-primary" type="submit" disabled title="Creating the Google event is not built yet">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                            Schedule and send invites
                        </button>
                        <p class="pay-hint pay-hint-block">
                            If Google refuses the request, the meeting is saved as
                            <strong>Requested</strong> and nothing is sent — you will
                            not be told it worked when it did not.
                        </p>
                    </div>
                </section>
            </aside>
        </section>
    </form>
@endsection
