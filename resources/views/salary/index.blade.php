@extends('layouts.app')

@php use App\Support\SalaryPresenter as P; @endphp

@section('title', 'Salary')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Salary Management</h1>
            <p>Payslips and payments for {{ P::period($period) }}.</p>
        </div>

        <div class="hd-actions">
            {{--
                How payroll actually gets paid.

                The bank file carries account number, IFSC and amount straight
                to the bank portal, so nobody has to read an account number off
                a screen. That is why bank details appear on no payroll page:
                paying somebody and browsing their details are different
                problems.

                TODO (backend phase): `salary.disburse`, an audit entry naming
                who generated it for which period, streamed from a controller
                rather than written anywhere public (§6).
            --}}
            <button class="btn btn-primary" type="button" disabled title="Generating the bank file is not built yet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Bank transfer file
            </button>

            <a class="btn btn-outline" href="{{ route('salary.mine') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h6"/>
                </svg>
                My Salary
            </a>
        </div>
    </div>

    {{--
        Two things worth stating on this page and nowhere else: pay is not
        calculated here, and everyone's is on it.
    --}}
    @include('partials.notice', [
        'tone' => 'info',
        'title' => 'Pay is worked out elsewhere; this is the record of it',
        'message' => 'Add the payslip your payroll produced, record the net it states, then mark the transfer done. Access needs the salary permission in its own right and is logged. Bank and Aadhaar details are shown on no payroll screen.',
    ])

    @include('salary.partials.kpis')

    @if ($withoutBanking->isNotEmpty() || $missing->isNotEmpty())
        @include('salary.partials.gaps')
    @endif

    @include('salary.partials.table')
@endsection
