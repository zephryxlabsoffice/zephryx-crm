@extends('layouts.app')

@php
    use App\Support\Avatar;
    use App\Support\ClientPresenter as CP;

    $status = CP::status($profile['status']);
@endphp

@section('title', 'My Profile')

@section('content')
    <div class="page-hd">
        <h1>My profile</h1>
        <p>Your organisation's details, and how to reach us.</p>
    </div>

    @include('client.partials.switcher')

    @include('partials.notice', [
        'tone' => 'info',
        'title' => 'Editing is not built yet',
        'message' => 'The fields below show what is on file. Changing your contact details lands with the backend.',
    ])

    <section class="dash-grid">
        <div class="dash-main">
            <section class="card">
                <div class="card-body cl-profile-hd">
                    <span class="avatar cl-profile-avatar {{ Avatar::tint($profile['name']) }}" aria-hidden="true">
                        {{ Avatar::initials($profile['name']) }}
                    </span>

                    <div class="cl-profile-id">
                        <h2>{{ $profile['name'] }}</h2>
                        <span class="dash-quiet-meta">{{ $profile['industry'] }}</span>
                        <span class="pill {{ $status['tone'] }}">{{ $status['label'] }}</span>
                    </div>
                </div>
            </section>

            {{--
                Two groups, and the split is the point of the page.

                What a client may change is a short list: how to reach them.
                Not the company name — invoices, projects and tickets reference
                a client by it, so letting the far side rewrite it would rename
                their own invoice history. Not the industry, and nothing about
                projects or what is owed.

                The read-only fields are rendered read-only AND excluded from
                the write: client.profile.update validates against
                ProfileController::EDITABLE and drops every other key. A
                disabled input is a courtesy to the reader, never the rule.
            --}}
            <form method="POST" action="{{ route('client.profile.update') }}">
                @csrf

                <div class="card">
                    <div class="card-hd">
                        <span class="card-title">Contact details</span>
                    </div>

                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-field">
                                <label class="form-field-lbl" for="cp-contact">Main contact</label>
                                <input id="cp-contact" name="contact_name" type="text" value="" placeholder="Who we usually speak to" disabled>
                            </div>

                            <div class="form-field">
                                <label class="form-field-lbl" for="cp-email">Email</label>
                                <input id="cp-email" name="contact_email" type="email" value="" placeholder="name@company.com" disabled>
                            </div>

                            <div class="form-field">
                                <label class="form-field-lbl" for="cp-phone">Phone</label>
                                <input id="cp-phone" name="contact_phone" type="tel" value="" placeholder="+91 98765 43210" disabled>
                            </div>

                            <div class="form-field cl-form-wide">
                                <label class="form-field-lbl" for="cp-address">Address</label>
                                <textarea id="cp-address" name="address" rows="3" placeholder="Where invoices should be addressed" disabled></textarea>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit" disabled title="Saving is not built yet">Save changes</button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="card">
                <div class="card-hd">
                    <span class="card-title">On file with us</span>
                </div>

                <div class="card-body">
                    <div class="field-grid">
                        <span class="field-lbl">Organisation</span>
                        <span class="field-val">{{ $profile['name'] }}</span>

                        <span class="field-lbl">Industry</span>
                        <span class="field-val">{{ $profile['industry'] }}</span>

                        <span class="field-lbl">Account status</span>
                        <span class="field-val">{{ $status['label'] }}</span>
                    </div>

                    {{-- Whose these are, said plainly, so nobody has to guess
                         why there is no edit button next to them. --}}
                    <p class="dash-note">
                        These are set by ZephryxLabs and referenced by your invoices
                        and projects. If something here is wrong, tell us and we will
                        correct it.
                    </p>
                </div>
            </div>
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Your work with us</strong>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Projects</span>
                    <span class="stat-value">{{ $stats['projects']['total'] }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Invoices</span>
                    <span class="stat-value">{{ $stats['invoices']['total'] }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Tickets raised</span>
                    <span class="stat-value">{{ $stats['tickets']['total'] }}</span>
                </div>
            </section>

            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Support</strong>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Email</span>
                    <span class="stat-value">
                        <a class="dash-link" href="{{ $support['mailto'] }}">{{ $support['email'] }}</a>
                    </span>
                </div>

                <p class="dash-note">
                    For anything about a specific project, a ticket reaches the team
                    working on it and stays attached to the project.
                </p>
            </section>
        </aside>
    </section>
@endsection
