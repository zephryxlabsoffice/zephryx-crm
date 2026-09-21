@extends('layouts.app')

@section('title', 'Account inactive')

{{--
    The Ex-Client page (client portal decisions, Q4, 2026-09-21).

    Going Inactive does not stop the login — it lands here instead. Invoices
    stay reachable and read-only through the one button below; everywhere
    else in this realm is a plain 403 now, not a redirect and not hidden. See
    App\Http\Controllers\Client\PortalController::requireActiveClient.
--}}
@section('content')
    <div class="page-hd">
        <h1>{{ $client }}</h1>
        <p>This engagement is marked inactive.</p>
    </div>

    <section class="card">
        <div class="card-body">
            <p class="rail-empty">
                Projects, tickets and meetings are no longer reachable from this account. Your invoice
                history is still here, and still yours to read and download.
            </p>

            <div class="dash-punch-action">
                <a class="btn btn-primary" href="{{ route('client.invoices.index') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                    </svg>
                    Go to invoices
                </a>
            </div>
        </div>
    </section>

    @include('client.partials.help')
@endsection
