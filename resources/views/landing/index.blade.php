@extends('layouts.public')

@section('title', 'ZephryxLabs CRM — Build Stronger Relationships')
@section('body-class', 'landing-body')
@section('description', 'The ZephryxLabs workspace — projects, teams, invoices and support in one place.')

@php
    $supportSubject = rawurlencode('ZephryxLabs CRM — support request');
    $supportMailto = 'mailto:'.config('zephryx.support.email').'?subject='.$supportSubject;
@endphp

@section('content')
    <section class="hero" aria-labelledby="hero-heading">

        {{-- ─────────────── copy column ─────────────── --}}
        <div class="hero-copy">
            <h1 class="hero-title" id="hero-heading">
                Build Stronger
                <span class="accent">Relationships.</span>
            </h1>

            <p class="hero-lede">
                The ZephryxLabs workspace — projects, teams, invoices and support in one
                place, for our people and the clients we build with.
            </p>

            <div class="cta-row">
                <a class="btn btn-primary" href="/login">Login</a>

                <a class="btn btn-outline" href="{{ $supportMailto }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 12a9 9 0 0 1 18 0v6a3 3 0 0 1-3 3h-1v-7h4"/>
                        <path d="M3 12v6a3 3 0 0 0 3 3h1v-7H3"/>
                    </svg>
                    Contact Support
                </a>
            </div>

            {{-- Feature strip. The handover's `stats` variant (50+ Happy Clients, 30%
                 More Productivity, 100% Data Secure) and the "GDPR Compliant" claim
                 are deliberately absent — see foundation spec §9.1. --}}
            <div class="info-strip" aria-label="What the workspace provides">
                <div class="info-item">
                    <div class="info-ic">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M23 4v6h-6"/>
                            <path d="M1 20v-6h6"/>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/>
                            <path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/>
                        </svg>
                    </div>
                    <div class="info-text">
                        <div class="info-num">Real-time</div>
                        <div class="info-lbl">Sync across teams</div>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-ic">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="11" width="18" height="11" rx="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </div>
                    <div class="info-text">
                        <div class="info-num">Role-based</div>
                        <div class="info-lbl">Access &amp; permissions</div>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-ic">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            <path d="M9 12l2 2 4-4"/>
                        </svg>
                    </div>
                    <div class="info-text">
                        <div class="info-num">Secure</div>
                        <div class="info-lbl">Encrypted &amp; audited</div>
                    </div>
                </div>
            </div>
        </div>

        @include('landing.partials.hero-visual')
    </section>

    <aside class="tagline-card">
        <div class="tagline-ic">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                <path d="M9 12l2 2 4-4"/>
            </svg>
        </div>
        <p>
            One workspace for projects, people and clients — so the work stays
            together instead of scattered across inboxes and spreadsheets.
        </p>
    </aside>
@endsection
