@extends('layouts.app')

@php
    use App\Models\ProjectUpdate;
@endphp

@section('title', 'Submit EOD · '.$project['name'])

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('projects.show', ['project' => $project['id']]) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                {{ $project['name'] }}
            </a>
            <h1>Today's update</h1>
            <p>{{ $project['id'] }} · {{ $project['client'] }}</p>
        </div>
    </div>

    <form class="an-form" method="POST" action="{{ route('projects.updates.store', ['project' => $project['id']]) }}">
        @csrf

        <section class="an-form-grid">
            <div class="an-form-main">
                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>
                        </svg>
                        What happened today
                    </div>

                    <div class="form-grid">
                        <div class="form-field an-form-wide">
                            <label class="form-field-lbl" for="upd-title">Headline</label>
                            <input id="upd-title" name="title" type="text" required maxlength="160"
                                   value="{{ old('title') }}">
                            <span class="pay-hint">One line somebody can scan in the log.</span>
                            @error('title')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field an-form-wide">
                            <label class="form-field-lbl" for="upd-body">The update</label>
                            <textarea id="upd-body" name="body" rows="8" required maxlength="5000">{{ old('body') }}</textarea>
                            @error('body')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                        Who can read this
                    </div>

                    @if ($mayPublish)
                        {{-- The radio, not a checkbox, and internal is
                             pre-selected. Forgetting hides something that should
                             have been shared — an annoyance, fixed by a click.
                             The other way round, forgetting publishes it, and
                             that is not fixable once it has been read. --}}
                        <div class="form-field">
                            <label class="check-row">
                                <input type="radio" name="visibility" value="{{ ProjectUpdate::INTERNAL }}"
                                       @checked(old('visibility', ProjectUpdate::INTERNAL) === ProjectUpdate::INTERNAL)>
                                <span>Internal only — the team, and nobody at the client</span>
                            </label>

                            <label class="check-row">
                                <input type="radio" name="visibility" value="{{ ProjectUpdate::CLIENT }}"
                                       @checked(old('visibility') === ProjectUpdate::CLIENT)>
                                <span>Share with {{ $project['client'] }}</span>
                            </label>
                        </div>

                        <div class="prose">
                            <p>
                                Sharing puts this in the client's portal. It can be hidden again
                                afterwards, but that is housekeeping rather than a recall — once
                                they have read it, they have read it.
                            </p>
                        </div>
                    @else
                        <div class="prose">
                            <p>
                                This update is internal. Somebody with permission to publish can
                                share it with the client afterwards, from the project's page.
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <aside class="rail">
                <section class="rail-card">
                    <div class="rail-hd">
                        <strong>Write it as it is</strong>
                    </div>

                    <div class="prose">
                        <p>
                            Internal is the default because a useful end-of-day note says what
                            actually happened — what is blocked, what was redone, what is waiting
                            on somebody. All of that is worth recording and most of it is not for
                            the client to read.
                        </p>
                    </div>

                    <button class="btn btn-primary" type="submit">Post update</button>

                    <a class="btn btn-outline btn-sm" href="{{ route('projects.show', ['project' => $project['id']]) }}">
                        Cancel
                    </a>
                </section>
            </aside>
        </section>
    </form>
@endsection
