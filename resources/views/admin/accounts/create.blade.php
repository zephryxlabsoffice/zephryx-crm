@extends('layouts.app')

@section('title', 'Add staff account')

{{-- See AccountController::create for why the Admin Panel can add people. --}}
@section('content')
    <div class="page-hd">
        <a class="back-link" href="{{ route('admin.accounts.index') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Accounts
        </a>
        <h1>Add staff account</h1>
        <p>
            They get an email to set their password. HR completes the rest of their
            record (department, documents, salary) in Employees.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.accounts.store') }}">
        @csrf

        <section class="card">
            <div class="card-hd">
                <span class="card-title">The person</span>
            </div>

            <div class="card-body">
                <div class="form-field">
                    <label class="form-field-lbl" for="acc-name">Full name</label>
                    <input id="acc-name" name="name" type="text" required maxlength="120" value="{{ old('name') }}">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-field">
                    <label class="form-field-lbl" for="acc-email">Email</label>
                    <input id="acc-email" name="email" type="email" required maxlength="190" value="{{ old('email') }}">
                    <span class="prose-quiet">The invite goes here, and it is what they sign in with.</span>
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-field">
                    <label class="form-field-lbl" for="acc-type">Employment</label>
                    <select id="acc-type" name="employment_type" required>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('employment_type', 'full_time') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('employment_type') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-field">
                    <label class="form-field-lbl" for="acc-joined">Joined on</label>
                    <input id="acc-joined" name="joined_on" type="date" required
                           max="{{ now()->toDateString() }}" value="{{ old('joined_on', now()->toDateString()) }}">
                    @error('joined_on') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-hd">
                <span class="card-title">Roles</span>
            </div>

            <div class="card-body">
                <p class="prose-quiet">Everybody is an Employee. Tick anything else they do — for the first account, that is usually HR.</p>

                <div class="ad-perms">
                    @foreach ($roles as $key => $role)
                        @continue($key === 'employee')
                        <label class="ad-perm">
                            <input type="checkbox" name="roles[]" value="{{ $key }}"
                                   @checked(in_array($key, (array) old('roles', []), true))>
                            <span class="ad-perm-body">
                                <span class="ad-perm-head">
                                    <strong>{{ $role['name'] }}</strong>
                                </span>
                                <span class="ad-perm-note">{{ $role['description'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('roles') <span class="field-error">{{ $message }}</span> @enderror

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Add and send invite</button>
                </div>
            </div>
        </section>
    </form>
@endsection
