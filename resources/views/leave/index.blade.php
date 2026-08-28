@extends('layouts.app')

@section('title', 'Leave Requests')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Leave Requests</h1>
            <p>Decide on time off, and see who is already away.</p>
        </div>

        <div class="hd-actions">
            <a class="btn btn-outline" href="{{ route('leave.mine') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                My Leave
            </a>
        </div>
    </div>

    @include('leave.partials.kpis')

    <section class="lv-grid">
        @include('leave.partials.queue')

        <aside class="rail">
            @include('leave.partials.absences')
            @include('leave.partials.policy')
        </aside>
    </section>
@endsection
