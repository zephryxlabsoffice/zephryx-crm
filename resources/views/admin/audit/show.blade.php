@extends('layouts.app')

@php
    use App\Support\Demo\DemoAudit;

    $meta = DemoAudit::kind($entry['kind']);
@endphp

@section('title', $entry['id'])

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('admin.audit.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Audit log
            </a>
            <h1>{{ $entry['action'] }}</h1>
            <p>{{ $entry['id'] }}</p>
        </div>
    </div>

    <section class="dash-grid">
        <div class="dash-main">
            <section class="card">
                <div class="card-hd">
                    <span class="card-title">What changed</span>
                    <span class="pill {{ $meta['tone'] }}">{{ $meta['label'] }}</span>
                </div>

                <div class="card-body">
                    {{--
                        Before and after, both, always (§6).

                        An entry recording that a value changed without saying
                        from what to what cannot answer the question anybody
                        arrives here with. And on a settings change the "after"
                        carries the EFFECT — "reclassified 6 days across 3
                        people" — because the new value alone does not describe
                        what happened to the records.
                    --}}
                    <div class="ad-change">
                        <div class="ad-change-side">
                            <span class="ad-change-lbl">Before</span>
                            <strong>{{ $entry['before'] !== '' ? $entry['before'] : '—' }}</strong>
                        </div>

                        <svg class="ad-change-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                        </svg>

                        <div class="ad-change-side is-proposed">
                            <span class="ad-change-lbl">After</span>
                            <strong>{{ $entry['after'] !== '' ? $entry['after'] : '—' }}</strong>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd">
                    <strong>The record</strong>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Actor</span>
                    <span class="stat-value">{{ $entry['actor'] }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Account type</span>
                    <span class="stat-value">{{ ucfirst($entry['actor_type']) }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Entity</span>
                    <span class="stat-value">{{ $entry['entity'] }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">When</span>
                    <span class="stat-value">{{ $entry['at']->format('d M Y, g:i A') }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">IP address</span>
                    <span class="stat-value">{{ $entry['ip'] }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Device</span>
                    <span class="stat-value">{{ $entry['agent'] }}</span>
                </div>
            </section>

            <section class="rail-card">
                <div class="rail-hd">
                    <strong>This entry cannot be changed</strong>
                </div>

                <p class="dash-note">
                    Nothing in this panel edits or removes an audit entry. The account that
                    can reach this page is the one whose actions most need a record, so its
                    own log is not its to tidy.
                </p>
            </section>
        </aside>
    </section>
@endsection
