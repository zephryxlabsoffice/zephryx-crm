@extends('layouts.app')

@php use App\Support\AnnouncementPresenter as P; @endphp

@section('title', 'Announcements')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Announcements</h1>
            <p>Notices we have put up for our clients.</p>
        </div>
    </div>

    <section class="dash-grid">
        <div class="dash-main">
            <section class="card">
                @if ($announcements->isEmpty())
                    <div class="card-body">
                        <p class="rail-empty">Nothing here yet.</p>
                    </div>
                @else
                    <div class="an-list">
                        @foreach ($announcements as $announcement)
                            <article class="card an-card @if ($announcement['pinned']) is-pinned @endif">
                                <div class="an-card-hd">
                                    @php $cat = P::category($announcement['category']); @endphp
                                    <span class="an-card-ic {{ $cat['tone'] }}" aria-hidden="true">
                                        @include('partials.nav-icon', ['icon' => $cat['icon']])
                                    </span>

                                    <div class="an-card-head">
                                        <h2 class="an-card-title">
                                            <a href="{{ route('client.announcements.show', ['announcement' => $announcement['id']]) }}">{{ $announcement['title'] }}</a>
                                        </h2>
                                        <p class="an-card-meta">
                                            <span class="an-chip {{ $cat['tone'] }}">{{ $cat['label'] }}</span>
                                            <span>{{ P::ago($announcement['published_at']) }}</span>
                                        </p>
                                    </div>
                                </div>

                                <p class="an-card-body">{{ $announcement['body'] }}</p>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <aside class="rail">
            @include('client.partials.help')
        </aside>
    </section>
@endsection
