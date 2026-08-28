@extends('layouts.app')

@php use App\Support\AnnouncementPresenter as P; @endphp

@section('title', 'Announcements')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Announcements</h1>
            <p>What the whole company should know.</p>
        </div>

        @if ($canPost)
            <div class="hd-actions">
                <a class="btn btn-primary" href="{{ route('announcements.create') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Post an announcement
                </a>
                <a class="btn btn-outline" href="{{ route('announcements.manage') }}">Manage</a>
            </div>
        @endif
    </div>

    <section class="an-grid">
        <div class="an-feed-col">
            @if ($category)
                <div class="an-filter-note">
                    Showing <strong>{{ P::category($category)['label'] }}</strong> only.
                    <a class="card-link" href="{{ route('announcements.index') }}">Show everything</a>
                </div>
            @endif

            @forelse ($announcements as $announcement)
                @include('announcements.partials.card', ['announcement' => $announcement])
            @empty
                <div class="card">
                    <div class="table-empty">
                        <strong>Nothing on the board.</strong>
                        @if ($category)
                            Nothing in this category right now — <a class="card-link" href="{{ route('announcements.index') }}">show everything</a>.
                        @else
                            Announcements appear here while they are live.
                        @endif
                    </div>
                </div>
            @endforelse
        </div>

        <aside class="rail">
            @include('announcements.partials.categories')
            @include('announcements.partials.upcoming')
            @include('announcements.partials.where')
        </aside>
    </section>
@endsection
