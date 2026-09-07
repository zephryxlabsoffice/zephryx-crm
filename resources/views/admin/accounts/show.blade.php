@extends('layouts.app')

@php
    use App\Support\Avatar;
    use App\Support\EmployeePresenter as EP;

    $status = EP::status($account['status']);
@endphp

@section('title', $account['name'])

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('admin.accounts.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Accounts
            </a>
            <h1>{{ $account['name'] }}</h1>
            <p>{{ $account['user_id'] }} · {{ $account['email'] }}</p>
        </div>
    </div>

    @include('partials.notice', [
        'tone' => 'info',
        'title' => 'Saving is not built yet',
        'message' => 'Roles and status are shown as they stand; the writes land with the backend.',
    ])

    <section class="dash-grid">
        <div class="dash-main">
            <section class="card">
                <div class="card-hd">
                    <span class="card-title">Roles</span>
                </div>

                <div class="card-body">
                    <p class="ad-group-note">
                        Roles stack. Somebody holding two has the union of both, never the
                        narrower of them (§2.4).
                    </p>

                    <form method="POST" action="{{ route('admin.accounts.roles', $account['user_id']) }}">
                        @csrf

                        <div class="ad-perms">
                            @foreach ($roles as $key => $role)
                                <label class="ad-perm">
                                    <input type="checkbox" name="roles[]" value="{{ $key }}"
                                           @checked(in_array($key, $held, true)) disabled>
                                    <span class="ad-perm-body">
                                        <span class="ad-perm-head">
                                            <strong>{{ $role['name'] }}</strong>
                                            <code>{{ count($role['permissions']) }} permissions</code>
                                        </span>
                                        <span class="ad-perm-note">{{ $role['description'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit" disabled title="Saving is not built yet">
                                Save roles
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="card">
                <div class="card-hd">
                    <span class="card-title">What this account can actually do</span>
                    <span class="dash-quiet-meta">{{ $effective->count() }} permissions</span>
                </div>

                <div class="card-body">
                    {{--
                        The union, spelled out.

                        This is the only place in the application that answers
                        "what can this person do" directly. Reading it off a list
                        of role names requires doing the union in your head, and
                        the whole point of §2.4 is that the union is larger than
                        any one role suggests.
                    --}}
                    @if ($effective->isEmpty())
                        <p class="rail-empty">Nothing. This account can sign in and do nothing.</p>
                    @else
                        <div class="ad-effective">
                            @foreach ($effective as $permission)
                                <code class="ad-effective-key">{{ $permission }}</code>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Account</strong>
                    <span class="pill {{ $status['tone'] }}">{{ $status['label'] }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Staff ID</span>
                    <span class="stat-value">{{ $account['user_id'] }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Department</span>
                    {{-- Shown, never edited here: the HR record belongs to
                         Employees, and an owner who could change somebody's
                         department from the access screen would be doing HR's
                         job with none of HR's context. --}}
                    <span class="stat-value">{{ $account['department'] }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Designation</span>
                    <span class="stat-value">{{ $account['designation'] }}</span>
                </div>

                <p class="dash-note">
                    Name, department and designation belong to the employee record and are
                    edited in Employees.
                </p>
            </section>

            @if ($sensitive->isNotEmpty())
                <section class="rail-card">
                    <div class="rail-hd">
                        <strong>Sensitive access</strong>
                    </div>

                    <p class="dash-note dash-note-warn">
                        This account holds {{ $sensitive->count() === 1 ? 'one permission' : $sensitive->count().' permissions' }}
                        worth knowing about.
                    </p>

                    @foreach ($sensitive as $key)
                        <div class="stat-row">
                            <span class="stat-label">{{ $key }}</span>
                            <span class="stat-value"><span class="is-soon">Held</span></span>
                        </div>
                    @endforeach
                </section>
            @endif

            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Sign-in</strong>
                </div>

                <form method="POST" action="{{ route('admin.accounts.status', $account['user_id']) }}">
                    @csrf

                    {{--
                        Suspending, never deleting. The person's attendance,
                        leave and payslips stay where they are and keep naming
                        them — a deleted user is a payroll record with nobody
                        attached to it.

                        TODO (backend phase): suspending must also kill live
                        sessions and trusted devices. An account that cannot sign
                        in but is already signed in is not suspended.
                    --}}
                    <p class="dash-note">
                        Suspending stops the sign-in. Their attendance, leave and payslips
                        are untouched and keep their name on them. Nothing here deletes an
                        account.
                    </p>

                    <div class="dash-punch-action">
                        <button class="btn btn-outline" type="submit" disabled title="Not built yet">
                            {{ $account['status'] === 'active' ? 'Suspend account' : 'Restore account' }}
                        </button>
                    </div>
                </form>
            </section>
        </aside>
    </section>
@endsection
