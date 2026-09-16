@extends('layouts.app')

@section('title', 'Employees')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Employees Management</h1>
            <p>Manage your team, roles and employee information.</p>
        </div>

        <div class="hd-actions">
            {{-- The change-request queue, with its count, for whoever may edit
                 a record. Here because this is where HR already is: a queue
                 reachable only by typing the URL is a queue nobody works. The
                 count is drawn even at zero so its absence never reads as "no
                 requests" when it actually means "you cannot see this". --}}
            @if ($mayEdit)
                <a class="btn btn-outline" href="{{ route('employees.requests.index') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <path d="M14 2v6h6M9 15l2 2 4-4"/>
                    </svg>
                    Change requests
                    @if ($pendingRequests > 0)
                        <span class="pill warning">{{ $pendingRequests }}</span>
                    @endif
                </a>
            @endif

            {{-- Hidden rather than disabled for somebody who cannot create.
                 A disabled button says "you may do this, later"; the honest
                 answer for a permission is that the action is not theirs. --}}
            @if ($mayCreate)
                <a class="btn btn-primary" href="{{ route('employees.create') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add Employee
                </a>
            @endif

            {{-- TODO (backend phase): bulk import creates accounts, so it needs
                 the `employees.create` permission, per-row validation, a dry-run
                 preview and an audit entry. A silent partial import is worse
                 than no import. --}}
            <button class="btn btn-outline" type="button" disabled title="Import is not built yet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                Import
            </button>

            {{-- TODO (backend phase): an export of the staff list carries
                 personal data, so it needs `employees.export` and an audit
                 entry naming who took it. --}}
            <button class="btn btn-outline" type="button" disabled title="Export is not built yet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Export
            </button>
        </div>
    </div>

    @include('employees.partials.kpis')

    <section class="emp-grid">
        @include('employees.partials.table')

        <aside class="rail">
            @include('employees.partials.departments')
            @include('employees.partials.starters')
            @include('employees.partials.birthdays')
            @include('employees.partials.quick-actions')
        </aside>
    </section>
@endsection
