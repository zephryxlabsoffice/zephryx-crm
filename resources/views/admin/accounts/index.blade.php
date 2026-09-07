@extends('layouts.app')

@php
    use App\Support\Avatar;
    use App\Support\EmployeePresenter as EP;
@endphp

@section('title', 'Accounts')

@section('content')
    <div class="page-hd">
        <h1>Accounts</h1>
        <p>Who can sign in, and what they may do once they have.</p>
    </div>

    {{--
        Not the employee directory. That is HR's, in Employees, and it owns the
        person's record — department, designation, reporting line. This owns the
        login and its roles, which is the owner's. See the head of
        App\Http\Controllers\Admin\AccountController.
    --}}
    <section class="kpi-row" aria-label="Account summary">
        <div class="kpi">
            <div class="kpi-ic tone-soft" aria-hidden="true">@include('partials.nav-icon', ['icon' => 'employees'])</div>
            <div class="kpi-body">
                <div class="kpi-lbl">Accounts</div>
                <div class="kpi-val">{{ $stats['total'] }}</div>
                <span class="kpi-sub">{{ $stats['active'] }} can sign in</span>
            </div>
        </div>

        <div class="kpi">
            <div class="kpi-ic {{ $stats['inactive'] > 0 ? 'tone-warn' : 'tone-soft' }}" aria-hidden="true">@include('partials.nav-icon', ['icon' => 'settings'])</div>
            <div class="kpi-body">
                <div class="kpi-lbl">Cannot sign in</div>
                <div class="kpi-val">{{ $stats['inactive'] }}</div>
                <span class="kpi-sub">Their records stay exactly where they are</span>
            </div>
        </div>

        <div class="kpi">
            <div class="kpi-ic {{ $stats['no_roles'] > 0 ? 'tone-warn' : 'tone-soft' }}" aria-hidden="true">@include('partials.nav-icon', ['icon' => 'teams'])</div>
            <div class="kpi-body">
                <div class="kpi-lbl">No roles</div>
                <div class="kpi-val">{{ $stats['no_roles'] }}</div>
                {{-- An account with no roles can sign in and do nothing, which
                     is almost never what whoever created it intended. --}}
                <span class="kpi-sub">Can sign in and do nothing</span>
            </div>
        </div>
    </section>

    <section class="card table-card">
        <div class="card-hd">
            <span class="card-title">All accounts</span>
        </div>

        <div class="card-body-table">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Account</th>
                        <th scope="col">Roles</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($accounts as $account)
                        @php $status = EP::status($account['status']); @endphp
                        <tr>
                            <td>
                                <a class="row-link person-row" href="{{ route('admin.accounts.show', $account['user_id']) }}">
                                    <span class="avatar {{ Avatar::tint($account['name']) }}" aria-hidden="true">
                                        {{ Avatar::initials($account['name']) }}
                                    </span>
                                    <span class="person-body">
                                        <strong>{{ $account['name'] }}</strong>
                                        <span>{{ $account['user_id'] }} · {{ $account['email'] }}</span>
                                    </span>
                                </a>
                            </td>
                            <td>
                                @if ($account['roles']->isEmpty())
                                    <span class="dash-quiet-meta">None</span>
                                @else
                                    <span class="ad-role-chips">
                                        @foreach ($account['roles'] as $role)
                                            <span class="chip">{{ $role['name'] }}</span>
                                        @endforeach
                                    </span>
                                @endif
                            </td>
                            <td class="cell-tight"><span class="pill {{ $status['tone'] }}">{{ $status['label'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
