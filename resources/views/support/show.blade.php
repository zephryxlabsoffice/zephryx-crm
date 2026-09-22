@extends('layouts.app')

@php use App\Support\SupportContact; @endphp

@section('title', 'Support')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Support</h1>
            <p>Two ways to reach us, depending on what it is.</p>
        </div>
    </div>

    <section class="dash-grid">
        <div class="dash-main">
            <section class="card">
                <div class="card-hd">
                    <span class="card-title">Raise a ticket</span>
                </div>

                <div class="card-body">
                    <p class="dash-note">
                        For anything that should go into the queue and get triaged —
                        something broken, a request that needs routing, or work that
                        should be tracked to a close. It arrives unassigned and
                        unprioritised, and whoever is on triage sets both.
                    </p>

                    <a class="btn btn-primary" href="{{ route('tickets.create') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Raise a ticket
                    </a>
                </div>
            </section>

            <section class="card">
                <div class="card-hd">
                    <span class="card-title">Email us</span>
                </div>

                <div class="card-body">
                    <p class="dash-note">
                        For anything that is not a ticket — a question, something
                        urgent, or something that should not sit in a queue.
                    </p>

                    <a class="btn btn-outline" href="{{ SupportContact::mailto() }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 4h16v16H4z"/><path d="m22 6-10 7L2 6"/>
                        </svg>
                        {{ SupportContact::address() }}
                    </a>
                </div>
            </section>
        </div>
    </section>
@endsection
