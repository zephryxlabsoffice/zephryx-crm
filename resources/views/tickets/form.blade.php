@extends('layouts.app')

@section('title', 'Raise a ticket')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('tickets.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Tickets
            </a>
            <h1>Raise a ticket</h1>
            <p>It goes into the queue for triage. Nobody sets their own priority.</p>
        </div>
    </div>

    <form class="an-form" method="POST" action="{{ route('tickets.store') }}">
        @csrf

        <section class="an-form-grid">
            <div class="an-form-main">
                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 10V8a2 2 0 0 0-2-2h-4l-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2"/>
                            <path d="M21 10a2 2 0 0 0 0 4"/>
                        </svg>
                        What is wrong
                    </div>

                    <div class="form-grid">
                        <div class="form-field an-form-wide">
                            <label class="form-field-lbl" for="ticket-subject">Subject</label>
                            <input id="ticket-subject" name="subject" type="text" required maxlength="200"
                                   value="{{ old('subject') }}">
                            <span class="pay-hint">One line somebody triaging can act on.</span>
                            @error('subject')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field an-form-wide">
                            <label class="form-field-lbl" for="ticket-description">What happened</label>
                            <textarea id="ticket-description" name="description" rows="6" required maxlength="5000">{{ old('description') }}</textarea>
                            <span class="pay-hint">What you did, what you expected, and what happened instead.</span>
                            @error('description')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        </svg>
                        Who it is for
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="ticket-type">Type</label>
                            <select id="ticket-type" name="type" required>
                                <option value="internal" @selected(old('type', 'internal') === 'internal')>
                                    Internal — something here needs fixing
                                </option>
                                <option value="client" @selected(old('type') === 'client')>
                                    Client — raised on a client's behalf
                                </option>
                            </select>
                            @error('type')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="ticket-client">Client <span class="an-optional">(client tickets only)</span></label>
                            <select id="ticket-client" name="client_id">
                                <option value="">Not a client ticket</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected((int) old('client_id') === $client->id)>
                                        {{ $client->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('client_id')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="ticket-project">Project <span class="an-optional">(optional)</span></label>
                            <select id="ticket-project" name="project_id">
                                <option value="">Not about a project</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected((int) old('project_id') === $project->id)>
                                        {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('project_id')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="ticket-supersedes">Replaces ticket <span class="an-optional">(optional)</span></label>
                            <input id="ticket-supersedes" name="supersedes" type="text" maxlength="32"
                                   placeholder="e.g. TKT-2026-014" value="{{ old('supersedes') }}">
                            <span class="pay-hint">If this continues an earlier ticket, reference it here — it will be closed automatically.</span>
                            @error('supersedes')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <aside class="rail">
                <section class="rail-card">
                    <div class="rail-hd">
                        <strong>What happens next</strong>
                    </div>

                    <div class="prose">
                        <p>
                            The ticket arrives unassigned and without a priority, and
                            whoever is on triage sets both. That is deliberate: a form
                            where everybody picks their own priority is a queue where
                            everything is urgent, which is the same as nothing being.
                        </p>
                        <p>
                            The reference is assigned automatically — {{ $reference }} —
                            and never reused.
                        </p>
                    </div>

                    <button class="btn btn-primary" type="submit">Raise ticket</button>

                    <a class="btn btn-outline btn-sm" href="{{ route('tickets.index') }}">Cancel</a>
                </section>
            </aside>
        </section>
    </form>
@endsection
