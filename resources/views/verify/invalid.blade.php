@extends('layouts.verify')

@section('title', $reason === 'missing' ? 'Not found' : 'Not a genuine link')

@section('content')
    <section class="vf-card vf-card-bad">
        <div class="vf-badge vf-badge-bad">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
            {{ $reason === 'missing' ? 'Not found' : 'Could not verify' }}
        </div>

        @if ($reason === 'missing')
            <h1 class="vf-name">No such employee</h1>
            <p class="vf-note">This ID does not belong to anybody who works, or has worked, at {{ config('zephryx.brand.name') }}.</p>
        @else
            <h1 class="vf-name">This link is not genuine</h1>
            <p class="vf-note">
                It may have been altered, or it is a search link that has expired. Scan the QR
                code on the card again, or search for the Employee ID below. If the card's QR
                code itself shows this, the card may not be genuine: please contact us.
            </p>
        @endif

        <a class="btn btn-primary" href="{{ route('verify.landing') }}">Search an Employee ID</a>
    </section>

    @include('verify.partials.contact')
@endsection
