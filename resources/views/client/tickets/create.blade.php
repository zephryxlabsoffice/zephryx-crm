@extends('layouts.app')

@section('title', 'Raise a ticket')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('client.tickets.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Support tickets
            </a>
            <h1>Raise a ticket</h1>
            <p>It reaches the team working on your project and stays attached to it.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('client.tickets.store') }}">
        @csrf

        <section class="dash-grid">
            <div class="dash-main">
                <div class="card">
                    <div class="card-hd">
                        <span class="card-title">What has gone wrong</span>
                    </div>

                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-field cl-form-wide">
                                <label class="form-field-lbl" for="tk-subject">Subject</label>
                                <input id="tk-subject" name="subject" type="text" maxlength="200" required
                                       value="{{ old('subject') }}"
                                       placeholder="Contact form is not sending enquiries">
                                <span class="pay-hint">One line. What the problem is, not how urgent it feels.</span>
                            </div>

                            <div class="form-field cl-form-wide">
                                <label class="form-field-lbl" for="tk-description">Details</label>
                                <textarea id="tk-description" name="description" rows="6" maxlength="5000" required
                                          placeholder="What you did, what you expected, and what happened instead. A page address helps.">{{ old('description') }}</textarea>
                            </div>

                            <div class="form-field">
                                <label class="form-field-lbl" for="tk-project">Project</label>
                                {{--
                                    Only this client's projects, and the store
                                    route must re-check the posted value against
                                    the session's client rather than trusting
                                    it. A select is a form field, and a form
                                    field is something anyone can type into —
                                    otherwise raising a ticket becomes a way to
                                    file one against somebody else's project.
                                --}}
                                <select id="tk-project" name="project">
                                    <option value="">Not about a specific project</option>
                                    @foreach ($projects as $project)
                                        <option value="{{ $project['id'] }}" @selected(old('project') === $project['id'])>
                                            {{ $project['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{--
                                The urgency select that was here is gone.

                                A ticket arrives unprioritised, and an
                                unprioritised ticket is one nobody has looked at
                                — not one judged unimportant. The queue sorts on
                                that difference. Letting the person raising it
                                set the priority makes every ticket high, which
                                is the same as none of them being, and the staff
                                form does not offer it either for the same
                                reason.

                                A field the write deliberately ignores is worse
                                than no field: somebody sets it to Critical and
                                believes they have been heard.
                            --}}
                        </div>
                    </div>
                </div>
            </div>

            <aside class="rail">
                <section class="rail-card">
                    <div class="rail-hd">
                        <strong>What happens next</strong>
                    </div>

                    <p class="dash-note">
                        The ticket goes into the support queue and someone picks it
                        up. You will see it move through Open, In Progress and
                        Resolved on this page — and every reply from us appears on
                        the ticket itself.
                    </p>

                    <div class="dash-punch-action">
                        <button class="btn btn-primary" type="submit">Submit ticket</button>
                    </div>
                </section>

                @include('client.partials.help')
            </aside>
        </section>
    </form>
@endsection
