@extends('layouts.app')

@section('title', 'Request a meeting')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('client.meetings.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Meetings
            </a>
            <h1>Request a meeting</h1>
            <p>Tell us what you want to cover and roughly when suits.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('client.meetings.request') }}">
        @csrf

        <section class="dash-grid">
            <div class="dash-main">
                <div class="card">
                    <div class="card-hd">
                        <span class="card-title">What you would like to discuss</span>
                    </div>

                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-field cl-form-wide">
                                <label class="form-field-lbl" for="mt-title">Subject</label>
                                <input id="mt-title" name="title" type="text" maxlength="200" required
                                       placeholder="Walk through the admissions page" value="{{ old('title') }}">
                                @error('title')
                                    <span class="field-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-field cl-form-wide">
                                <label class="form-field-lbl" for="mt-agenda">What to cover</label>
                                <textarea id="mt-agenda" name="agenda" rows="5" maxlength="2000"
                                          placeholder="A couple of lines is plenty — it helps us bring the right person.">{{ old('agenda') }}</textarea>
                                @error('agenda')
                                    <span class="field-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-field">
                                <label class="form-field-lbl" for="mt-project">Project</label>
                                {{-- Their own projects, re-checked server-side
                                     against the session's client on submit. --}}
                                <select id="mt-project" name="project_id">
                                    <option value="">Not about a specific project</option>
                                    @foreach ($projects as $project)
                                        <option value="{{ $project['key'] }}" @selected((int) old('project_id') === $project['key'])>{{ $project['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('project_id')
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
                                <label class="form-field-lbl" for="mt-date">Preferred date</label>
                                <input id="mt-date" name="date" type="date" required min="{{ $today }}" value="{{ old('date') }}">
                                @error('date')
                                    <span class="field-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-field">
                                <label class="form-field-lbl" for="mt-time">Preferred time</label>
                                <input id="mt-time" name="time" type="time" required value="{{ old('time') }}">
                                {{-- Times are shown and entered in the office's
                                     timezone and stored UTC — every date on a
                                     meeting record is UTC, which is the bug
                                     MeetingPresenter::statusOf was fixed for. --}}
                                <span class="pay-hint">All times {{ $zone }}. We will confirm the exact slot.</span>
                                @error('time')
                                    <span class="field-error">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="rail">
                <section class="rail-card">
                    <div class="rail-hd">
                        <strong>What happens next</strong>
                    </div>

                    {{--
                        Said plainly, because the difference between a request
                        and a meeting is the thing people get wrong: a request
                        produces no calendar event, so nothing is in anybody's
                        diary until we create it.
                    --}}
                    <p class="dash-note">
                        This is a request, not a booking. Nothing goes into a
                        calendar until we confirm it — you will see it as
                        "Awaiting confirmation" until then, and the invitation
                        arrives by email once a time is set.
                    </p>

                    <div class="dash-punch-action">
                        <button class="btn btn-primary" type="submit">
                            Send request
                        </button>
                    </div>
                </section>

                @include('client.partials.help')
            </aside>
        </section>
    </form>
@endsection
