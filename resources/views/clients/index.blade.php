@extends('layouts.app')

@section('title', 'Clients')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Client Management</h1>
            <p>Manage, monitor and organise every client from one place.</p>
        </div>

        <div class="hd-actions">
            {{-- Hidden rather than disabled for somebody without the
                 permission: a button that leads straight to a 403 is worse than
                 no button. The route is guarded either way. --}}
            @if ($mayCreate)
                <a class="btn btn-primary" href="{{ route('clients.create') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add Client
                </a>
            @endif

            {{-- TODO (backend phase): streams a file, so it needs the
                 `clients.export` permission and an audit-log entry — an export
                 is the fastest way a whole table leaves the building. --}}
            <button class="btn btn-outline" type="button" disabled title="Export is not built yet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Export Data
            </button>
        </div>
    </div>

    @include('clients.partials.kpis')

    <section class="cl-grid">
        @include('clients.partials.table')

        <aside class="rail">
            @include('clients.partials.activity')
            @include('clients.partials.meetings')
            @include('clients.partials.quick-actions')
        </aside>
    </section>
@endsection
