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
                            <input id="mt-title" name="title" type="text" maxlength="200" required
                                   placeholder="Sprint planning, invoice query…" value="{{ old('title') }}">
                            <span class="pay-hint">This is what everyone sees in their calendar.</span>
                            @error('title')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="mt-date">Date</label>
                            <input id="mt-date" name="date" type="date" required value="{{ old('date') }}">
                            @error('date')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="mt-time">Start time</label>
                            <input id="mt-time" name="time" type="time" required value="{{ old('time') }}">
                            {{-- Says which clock. Times are stored in UTC and
                                 shown in one zone, so what is typed here is
                                 unambiguous even when the client is abroad. --}}
                            <span class="pay-hint">{{ $zone }}. Stored as UTC so it cannot drift when clocks change.</span>
                            @error('time')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="mt-duration">Length</label>
                            <select id="mt-duration" name="duration">
                                @foreach ([15, 30, 45, 60, 90] as $minutes)
                                    <option value="{{ $minutes }}" @selected($minutes === (int) old('duration', $defaultDuration))>{{ $minutes }} minutes</option>
                                @endforeach
                            </select>
                            @error('duration')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="mt-project">Project <span class="mt-optional">(optional)</span></label>
                            <select id="mt-project" name="project_id">
                                <option value="">Internal — no project</option>
                                @foreach ($projects as $option)
                                    <option value="{{ $option['key'] }}" @selected((int) old('project_id') === $option['key'])>{{ $option['name'] }}</option>
                                @endforeach
                            </select>
                            @error('project_id')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field mt-form-wide">
                            <label class="form-field-lbl" for="mt-agenda">What it is about</label>
                            <textarea id="mt-agenda" name="agenda" rows="3" maxlength="2000" placeholder="A line is enough — it goes into the invite.">{{ old('agenda') }}</textarea>
                            @error('agenda')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
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

                    @php $picked = array_map('intval', (array) old('attendees', [])); @endphp

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="mt-staff">Colleagues</label>
                            {{-- A multiple select, not a JavaScript token field:
                                 it works without a script, submits an array the
                                 backend can validate, and is operable from a
                                 keyboard by default. Both selects post into the
                                 same `attendees[]` — an invite is to a person,
                                 staff or client alike. --}}
                            <select id="mt-staff" name="attendees[]" multiple size="6">
                                @foreach ($employees as $person)
                                    @continue($person['id'] === null)
                                    <option value="{{ $person['id'] }}" @selected(in_array($person['id'], $picked, true))>{{ $person['name'] }} — {{ $person['detail'] }}</option>
                                @endforeach
                            </select>
                            <span class="pay-hint">Ctrl or Cmd to pick more than one. You are always on the invite.</span>
                            @error('attendees.*')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="mt-clients">Clients <span class="mt-optional">(optional)</span></label>
                            <select id="mt-clients" name="attendees[]" multiple size="6">
                                @foreach ($clients as $person)
                                    <option value="{{ $person['id'] }}" @selected(in_array($person['id'], $picked, true))>{{ $person['name'] }} — {{ $person['detail'] }}</option>
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
                        <button class="btn btn-primary" type="submit">
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
