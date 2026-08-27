@extends('layouts.app')

@php
    use App\Support\Avatar;
    use App\Support\SalaryPresenter as P;
@endphp

@section('title', 'Payslip · '.P::periodShort($run['period']))

@section('content')
    @php $pill = P::status($run['status']); @endphp

    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ $own ? route('salary.mine') : route('salary.index', ['period' => $run['period']]) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                {{ $own ? 'My salary' : 'Payroll' }}
            </a>
            <h1>Payslip · {{ P::period($run['period']) }}</h1>
            <p>{{ $run['employee_record']['name'] }} · {{ $run['employee'] }}</p>
        </div>

        <div class="hd-actions">
            <button class="btn btn-outline" type="button" disabled title="PDF generation is not built yet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Download PDF
            </button>
        </div>
    </div>

    @if ($run['status'] === P::ON_HOLD)
        @include('partials.notice', [
            'tone' => 'warning',
            'title' => 'This month’s pay is on hold',
            'message' => 'Somebody put it on hold deliberately — it is not a system state. If you were not told why, ask HR.',
        ])
    @elseif ($run['status'] === P::PENDING)
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'Generated, not yet paid',
            'message' => 'The figures below are final; the transfer has not been released yet.',
        ])
    @endif

    @if (! $own && $identity === null)
        {{--
            Said out loud rather than silently omitted, so nobody assumes the
            page is broken and goes looking for the fields elsewhere.
        --}}
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'Bank, PAN and Aadhaar are not shown here',
            'message' => 'Those appear only on a person’s own salary page. Needing them for a transfer or a filing is a separate, logged request — not something a page hands over because of who is looking at it.',
        ])
    @endif

    <section class="ms-grid">
        <div class="ms-main">
            <div class="card sl-slip-hd">
                <div class="inv-doc-hd">
                    <div class="inv-doc-brand">
                        @include('partials.brand-mark')
                        <div>
                            <strong>ZephryxLabs</strong>
                            <span>Kolkata, India</span>
                        </div>
                    </div>
                    <div class="inv-doc-meta">
                        <span class="inv-doc-kind">Payslip</span>
                        <strong class="inv-doc-no">{{ P::period($run['period']) }}</strong>
                        <span class="inv-doc-cur">All amounts in {{ $run['currency'] }}</span>
                    </div>
                </div>

                <div class="sl-slip-person">
                    <span class="avatar {{ Avatar::tint($run['employee_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($run['employee_record']['name']) }}</span>
                    <div class="sl-slip-person-body">
                        <strong>{{ $run['employee_record']['name'] }}</strong>
                        <span>{{ $run['employee'] }} · {{ $run['employee_record']['designation'] }} · {{ $run['employee_record']['department'] }}</span>
                    </div>
                    <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                </div>
            </div>

            @include('salary.partials.breakdown', ['heading' => 'Pay for this month'])
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd"><strong>This run</strong></div>
                <div>
                    <div class="stat-row">
                        <span class="stat-label">Month</span>
                        <span class="stat-value">{{ P::period($run['period']) }}</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Status</span>
                        <span class="stat-value">{{ $pill['label'] }}</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Paid on</span>
                        <span class="stat-value">{{ P::paidOn($run) }}</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Method</span>
                        <span class="stat-value">{{ $run['method'] }}</span>
                    </div>
                    <div class="stat-row stat-row-block">
                        <span class="stat-label">What it means</span>
                        <span class="stat-value stat-value-quiet">{{ $pill['meaning'] }}</span>
                    </div>
                </div>
            </section>

            @if ($identity !== null)
                @include('salary.partials.identity', ['latest' => $run])
            @endif
        </aside>
    </section>
@endsection
