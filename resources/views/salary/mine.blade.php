@extends('layouts.app')

@php use App\Support\SalaryPresenter as P; @endphp

@section('title', 'My Salary')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
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
                @include('salary.partials.breakdown', ['run' => $latest, 'heading' => 'This month’s pay'])
                @include('salary.partials.history')
            </div>

            <aside class="rail">
                @include('salary.partials.identity')
            </aside>
        </section>
    @endif
@endsection
