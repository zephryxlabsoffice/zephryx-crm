@extends('layouts.verify')

@php
    $working = $card['state'] === \App\Support\Verify\VerifyDirectory::WORKING;
    $brand = config('zephryx.brand.name');
@endphp

@section('title', $card['name'])

@section('content')
    <section class="vf-card {{ $working ? 'vf-card-good' : 'vf-card-bad' }}">
        @if ($working)
            <div class="vf-badge vf-badge-good">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                Currently working at {{ $brand }}
            </div>

            <div class="vf-photo">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="Photo of {{ $card['name'] }}" width="140" height="140">
                @else
                    <span class="vf-initials" aria-hidden="true">{{ \App\Support\Shell::initials($card['name']) }}</span>
                @endif
            </div>

            <h1 class="vf-name">{{ $card['name'] }}</h1>
            <p class="vf-role">{{ $card['designation'] ?? '-' }}</p>

            <dl class="vf-facts">
                <div><dt>Department</dt><dd>{{ $card['department'] ?? '-' }}</dd></div>
                <div><dt>Engagement</dt><dd>{{ $card['type'] ?? '-' }}</dd></div>
                <div><dt>Employee ID</dt><dd class="vf-mono">{{ $card['id'] }}</dd></div>
            </dl>

            @if ($card['earlier_id'])
                <p class="vf-note">
                    {{ $card['earlier_id'] }} was {{ $card['name'] }}'s internship ID. They now work
                    here under {{ $card['id'] }}.
                </p>
            @endif
        @else
            <div class="vf-badge vf-badge-bad">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                Not currently with {{ $brand }}
            </div>

            <h1 class="vf-name">{{ $card['name'] }}</h1>

            <dl class="vf-facts">
                <div><dt>Employee ID</dt><dd class="vf-mono">{{ $card['id'] }}</dd></div>
                <div><dt>Left on</dt><dd>{{ $card['left_on']?->format('j F Y') ?? 'Not recorded' }}</dd></div>
            </dl>

            <p class="vf-note">An ID card with this ID should no longer be accepted as current.</p>
        @endif

        <p class="vf-checked">Checked {{ $checkedAt->format('j M Y, g:i A') }} (IST)</p>
    </section>

    @include('verify.partials.contact')
@endsection
