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

    @include('partials.notice', [
        'tone' => 'info',
        'title' => 'This form does not submit yet',
        'message' => 'The fields and the validation shape are real; the write lands with the backend. Nothing typed here is stored and nobody is notified.',
    ])

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
                                <input id="mt-title" name="title" type="text"
                                       placeholder="Walk through the admissions page" disabled>
                            </div>

                            <div class="form-field cl-form-wide">
                                <label class="form-field-lbl" for="mt-agenda">What to cover</label>
                                <textarea id="mt-agenda" name="agenda" rows="5"
                                          placeholder="A couple of lines is plenty — it helps us bring the right person." disabled></textarea>
                            </div>

                            <div class="form-field">
                                <label class="form-field-lbl" for="mt-project">Project</label>
                                {{-- Their own projects, re-checked server-side
                                     against the session's client on submit. --}}
                                <select id="mt-project" name="project" disabled>
                                    <option value="">Not about a specific project</option>
                                    @foreach ($projects as $project)
                                        <option value="{{ $project['id'] }}">{{ $project['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-field">
                                <label class="form-field-lbl" for="mt-preferred">Roughly when</label>
                                <input id="mt-preferred" name="preferred_at" type="datetime-local" disabled>
                                {{-- Times are shown and entered in the office's
                                     timezone and stored UTC — every date on a
                                     meeting record is UTC, which is the bug
                                     MeetingPresenter::statusOf was fixed for. --}}
                                <span class="pay-hint">All times {{ $zone }}. We will confirm the exact slot.</span>
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
                        <button class="btn btn-primary" type="submit" disabled title="Requesting a meeting is not built yet">
                            Send request
                        </button>
                    </div>
                </section>

                @include('client.partials.help')
            </aside>
        </section>
    </form>
@endsection
