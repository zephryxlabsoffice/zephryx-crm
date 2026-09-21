@extends('layouts.app')

@php use App\Support\AnnouncementPresenter as P; @endphp

@section('title', $announcement['title'])

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('client.announcements.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Announcements
            </a>
            <h1>{{ $announcement['title'] }}</h1>
            <p>{{ P::ago($announcement['published_at']) }}</p>
        </div>
    </div>

    <section class="dash-grid">
        <div class="dash-main">
            <section class="card">
                <div class="card-body">
                    @php $cat = P::category($announcement['category']); @endphp
                    <span class="an-chip {{ $cat['tone'] }}">{{ $cat['label'] }}</span>
                    <p class="cl-ticket-body">{{ $announcement['body'] }}</p>
                </div>
            </section>
        </div>

        <aside class="rail">
            @include('client.partials.help')
        </aside>
    </section>
@endsection
