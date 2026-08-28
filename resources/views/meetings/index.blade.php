@extends('layouts.app')

@section('title', 'Meetings')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Meetings</h1>
            <p>Organised here, hosted on Google Meet.</p>
        </div>

        <div class="hd-actions">
            <a class="btn btn-primary" href="{{ route('meetings.create') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Schedule meeting
            </a>
        </div>
    </div>

    @include('meetings.partials.kpis')

    <section class="mt-grid">
        @include('meetings.partials.table')

        <aside class="rail">
            @include('meetings.partials.next')
            @include('meetings.partials.hosting')
        </aside>
    </section>
@endsection
