@extends('layouts.app')

@php
    use App\Support\Avatar;
    use App\Support\SalaryPresenter as P;
    $status = P::statusOf($record);
    $pill = P::status($status);
@endphp

@section('title', P::periodShort($record['period']).' · '.$record['employee_record']['name'])

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ $own ? route('salary.mine') : route('salary.index', ['period' => $record['period']]) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                {{ $own ? 'My salary' : 'Payroll' }}
            </a>
            <h1>{{ P::period($record['period']) }}</h1>
            <p>{{ $record['employee_record']['name'] }} · {{ $record['employee'] }}</p>
        </div>

        @if ($record['payslip'])
            <div class="hd-actions">
                {{-- A real route. §6: the file is stored outside the web root
                     and streamed by a controller that checks the viewer may
                     have it — never linked at a public path. --}}
                <a class="btn btn-outline" href="{{ route('salary.payslip.download', ['employee' => $record['employee'], 'period' => $record['period']]) }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    Download payslip
                </a>
            </div>
        @endif
    </div>

    @if ($status === P::NO_PAYSLIP && $own)
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'Nothing added for this month yet',
            'message' => 'Your payslip appears here once payroll has been run. If the month has closed and it still is not here, ask HR.',
        ])
    @elseif (! $own && $identity === null)
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'Bank, PAN and Aadhaar are not shown here',
            'message' => 'Paying somebody does not need them on screen — the bank transfer file on the payroll page carries account number, IFSC and amount straight to the bank. If a transfer bounces and one account has to be checked, that is a separate, logged reveal of that one record.',
        ])
    @endif

    <section class="ms-grid">
        <div class="ms-main">
            <div class="card">
                <div class="sl-slip-person">
                    <span class="avatar {{ Avatar::tint($record['employee_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($record['employee_record']['name']) }}</span>
                    <div class="sl-slip-person-body">
                        <strong>{{ $record['employee_record']['name'] }}</strong>
                        <span>{{ $record['employee'] }} · {{ $record['employee_record']['designation'] }} · {{ $record['employee_record']['department'] }}</span>
                    </div>
                    <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                </div>

                <div class="sl-net sl-net-lone">
                    <span class="sl-net-lbl">Net pay</span>
                    <strong class="sl-net-val money">{{ P::net($record) }}</strong>
                    <span class="sl-net-note">
                        {{-- The application does not work this figure out; it
                             records what the payslip states. Saying so is the
                             difference between a number people trust and one
                             they argue with. --}}
                        As stated on the payslip · {{ P::paidOn($record) }}
                    </span>
                </div>
            </div>

            @if (! $own)
                @include('salary.partials.manage')
            @endif
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd"><strong>This month</strong></div>
                <div>
                    <div class="stat-row">
                        <span class="stat-label">Status</span>
                        <span class="stat-value">{{ $pill['label'] }}</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Payslip</span>
                        <span class="stat-value">{{ $record['payslip'] ? P::date($record['payslip']['added_on']) : 'Not added' }}</span>
                    </div>
                    @if ($record['payslip'])
                        <div class="stat-row">
                            <span class="stat-label">Added by</span>
                            <span class="stat-value">{{ $record['payslip']['added_by'] }}</span>
                        </div>
                    @endif
                    <div class="stat-row">
                        <span class="stat-label">Paid on</span>
                        <span class="stat-value">{{ P::paidOn($record) }}</span>
                    </div>
                    <div class="stat-row stat-row-block">
                        <span class="stat-label">What it means</span>
                        <span class="stat-value stat-value-quiet">{{ $pill['meaning'] }}</span>
                    </div>
                </div>
            </section>

            @if ($identity !== null)
                @include('salary.partials.identity')
            @endif
        </aside>
    </section>
@endsection
