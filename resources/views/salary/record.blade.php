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

        {{-- Only when there is actually a file behind the record. A month can
             say a payslip was added and have none stored — an imported month,
             or one recorded before this application held the documents — and a
             download button that 404s is worse than no button. --}}
        @if ($record['payslip'] && ($record['payslip']['file'] ?? false))
            <div class="hd-actions">
                {{-- A real route. §6: the file is stored outside the web root
                     and streamed by a controller that checks the viewer may
                     have it — never linked at a public path.

                     Opens in the browser's own viewer, not as a download —
                     it lands on the document itself, the way a site backed
                     by an object store behaves. A "Save" button is already
                     in that viewer's own toolbar. --}}
                <a class="btn btn-outline" href="{{ route('salary.payslip.view', ['employee' => $record['employee'], 'period' => $record['period']]) }}" target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/>
                    </svg>
                    View payslip
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

            {{-- Payroll's own controls, and only for somebody who holds
                 `salary.manage`. Not shown on your own record at all: adding
                 your own payslip and marking yourself paid is the one shape
                 this module must not have. --}}
            @if (! $own && ($mayManage ?? false))
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
