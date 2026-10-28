@extends('layouts.app')

@section('title', 'Add account')

{{--
    See AccountController::create and ::storeNormal. Two kinds of account:
    an EMPLOYEE (attendance, leave, payslips) and a NORMAL account (hr@,
    admin@ - roles only, nobody's employment). Only this panel makes normal
    accounts; HR and CEO add employees in Employees.
--}}
@section('content')
    @php $normal = $kind === 'normal'; @endphp

    <div class="page-hd">
        <a class="back-link" href="{{ route($ar.'index') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Accounts
        </a>
        <h1>Add account</h1>
        <p>An email goes out to set the password.</p>
    </div>

    <nav class="tabs" aria-label="Kind of account">
        <a class="tab @if (! $normal) active @endif" href="{{ route($ar.'create') }}"
           @if (! $normal) aria-current="page" @endif>Employee</a>
        <a class="tab @if ($normal) active @endif" href="{{ route($ar.'create', ['kind' => 'normal']) }}"
           @if ($normal) aria-current="page" @endif>Normal account</a>
    </nav>

    <form method="POST" action="{{ route($ar.'store') }}">
        @csrf
        <input type="hidden" name="kind" value="{{ $kind }}">

        <section class="card">
            <div class="card-hd">
                <span class="card-title">{{ $normal ? 'The account' : 'The person' }}</span>
            </div>

            <div class="card-body">
                @if ($normal)
                    <p class="prose-quiet">
                        For a shared or office address such as hr@ or admin@. It does what its
                        roles allow and has no attendance, leave or payslips - it is nobody's
                        employment.
                    </p>
                @else
                    <p class="prose-quiet">
                        HR completes the rest of their record (department, documents, salary)
                        in Employees.
                    </p>
                @endif

                <div class="form-field">
                    <label class="form-field-lbl" for="acc-name">{{ $normal ? 'Name' : 'Full name' }}</label>
                    <input id="acc-name" name="name" type="text" required maxlength="120"
                           placeholder="{{ $normal ? 'HR Desk' : '' }}" value="{{ old('name') }}">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                @if ($normal)
                    <div class="form-field">
                        <label class="form-field-lbl" for="acc-email">Email</label>
                        <input id="acc-email" name="account_email" type="email" required maxlength="190"
                               placeholder="hr@zephryxlabs.in" value="{{ old('account_email') }}">
                        <span class="prose-quiet">It signs in with this, and the invite goes here.</span>
                        @error('account_email') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                @else
                    <div class="form-field">
                        <label class="form-field-lbl" for="acc-personal">Personal email</label>
                        <input id="acc-personal" name="personal_email" type="email" required maxlength="190" value="{{ old('personal_email') }}">
                        <span class="prose-quiet">They sign in with this, and the invite goes here, until they have a work email.</span>
                        @error('personal_email') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-field">
                        <label class="form-field-lbl" for="acc-work">Work email <span class="an-optional">(optional)</span></label>
                        <input id="acc-work" name="work_email" type="email" maxlength="190" value="{{ old('work_email') }}">
                        <span class="prose-quiet">If given, both emails sign in, and the invite and sign-in codes go here.</span>
                        @error('work_email') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-field">
                        <label class="form-field-lbl" for="acc-phone">Phone</label>
                        <input id="acc-phone" name="phone" type="tel" required maxlength="32" value="{{ old('phone') }}">
                        @error('phone') <span class="field-error">{{ $message }}</span> @enderror
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
                @endif
            </div>
        </section>

        <section class="card">
            <div class="card-hd">
                <span class="card-title">Roles</span>
            </div>

            <div class="card-body">
                <p class="prose-quiet">
                    @if ($normal)
                        Tick what this account does - for hr@, that is HR. At least one.
                    @else
                        Everybody is an Employee. Tick anything else they do - for the first account, that is usually HR.
                    @endif
                </p>

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
