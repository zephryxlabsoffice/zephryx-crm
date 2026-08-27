@extends('layouts.app')

@php use App\Support\SalaryPresenter as P; @endphp

@section('title', 'My Salary')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            {{--
                This page is reached by a button on Salary Management, so there
                has to be a way back — unlike My Teams and My Projects, which
                are navigation destinations and correctly have none.

                TODO (backend phase): render only for a viewer holding
                `salary.view.all`. Somebody who can only see their own pay never
                came from payroll and must not be shown a door into it.
            --}}
            @if ($canViewPayroll)
                <a class="back-link" href="{{ route('salary.index') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                    Payroll
                </a>
            @endif
            <h1>My Salary</h1>
            <p>Your pay, your payslips and what is on record for you.</p>
        </div>
    </div>

    @if ($latest === null)
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'No salary record yet',
            'message' => 'Nothing has been generated for you. If that seems wrong, speak to HR — this page shows what is on record, not what should be.',
        ])
    @else
        @include('salary.partials.mine-kpis')

        <section class="ms-grid">
            <div class="ms-main">
                @include('salary.partials.net-summary', ['run' => $latest])
                @include('salary.partials.history')
            </div>

            <aside class="rail">
                @include('salary.partials.identity')
            </aside>
        </section>
    @endif
@endsection
