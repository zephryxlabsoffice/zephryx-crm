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
        'message' => 'Access needs the salary permission in its own right, and is logged. Bank, PAN and Aadhaar details are never shown here — a person sees their own on My Salary.',
    ])

    @include('salary.partials.kpis')

    @if ($withoutStructure->isNotEmpty() || $missing->isNotEmpty())
        @include('salary.partials.gaps')
    @endif

    @include('salary.partials.table')
@endsection
