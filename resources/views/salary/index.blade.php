@extends('layouts.app')

@php use App\Support\SalaryPresenter as P; @endphp

@section('title', 'Salary')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Salary Management</h1>
            <p>Payroll for {{ P::period($period) }}.</p>
        </div>

        <div class="hd-actions">
            {{--
                How payroll actually gets paid.

                The bank file carries account number, IFSC and amount straight
                to the bank portal — so the person running payroll never has to
                read anybody's account number off a screen. That is the whole
                reason bank details are not rendered on this page: paying
                somebody and browsing their details are different problems.

                TODO (backend phase): `salary.disburse`, an audit entry naming
                who generated it for which period, and the file streamed from a
                controller rather than written anywhere public (§6).
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
        A standing reminder, on the one page where it is warranted.

        Every figure below is somebody's pay. §6 gives this page its own
        permission for exactly that reason — there is no "read-only so it is
        harmless" here, because reading it IS the harm.
    --}}
    @include('partials.notice', [
        'tone' => 'info',
        'title' => 'Everyone’s pay is on this page',
        'message' => 'Access needs the salary permission in its own right, and is logged. Bank and Aadhaar details are not shown on any payroll screen — the bank transfer file carries them to the bank without anyone having to read them.',
    ])

    @include('salary.partials.kpis')

    @if ($withoutStructure->isNotEmpty() || $missing->isNotEmpty())
        @include('salary.partials.gaps')
    @endif

    @include('salary.partials.table')
@endsection
