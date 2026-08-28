@extends('layouts.app')

@php
    use App\Support\AnnouncementPresenter as P;
    use App\Support\Avatar;
    $cat = P::category($announcement['category']);
    $pill = P::status($announcement['status']);
    $runs = P::runsUntil($announcement);
    $isMilestone = $announcement['kind'] === 'milestone';
@endphp

@section('title', $announcement['title'])

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('announcements.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Announcements
            </a>
            <h1>{{ $announcement['title'] }}</h1>
            <p>
                {{ $cat['label'] }}
                @if (! $isMilestone)
                    · posted by {{ $announcement['author_record']['name'] ?? 'Unknown' }}
                    · {{ P::dateTime($announcement['published_at']) }}
                @endif
            </p>
        </div>

        @if ($canPost && ! $isMilestone)
            <div class="hd-actions">
                @if ($announcement['status'] === P::DRAFT)
                    <form method="POST" action="{{ route('announcements.publish', ['announcement' => $announcement['id']]) }}">
                        @csrf
                        <button class="btn btn-primary" type="submit" disabled title="Publishing is not built yet">
                            Publish now
                        </button>
                    </form>
                @elseif ($announcement['status'] === P::ACTIVE)
                    {{-- Take it off the board, keep the record. What the company
                         said and when is worth being able to look up. --}}
                    <form method="POST" action="{{ route('announcements.expire', ['announcement' => $announcement['id']]) }}">
                        @csrf
                        <button class="btn btn-outline" type="submit" disabled title="Taking it down is not built yet">
                            Take off the board
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    @if ($announcement['status'] === P::DRAFT)
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'This is a draft',
            'message' => 'Nobody else can see it. It goes on the board when you publish it.',
        ])
    @elseif ($announcement['status'] === P::SCHEDULED)
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'Not on the board yet',
            'message' => 'It goes up on '.P::date($announcement['published_at']).'.',
        ])
    @elseif ($announcement['status'] === P::EXPIRED)
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'This has come off the board',
            'message' => 'It ran until '.P::date($announcement['expires_at']).'. It is kept so what was said and when can still be looked up.',
        ])
    @endif

    <section class="an-detail-grid">
        <div class="an-detail-main">
            <article class="card an-full">
                <div class="an-card-hd">
                    @if ($isMilestone)
                        <span class="avatar {{ Avatar::tint($announcement['employee_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($announcement['employee_record']['name']) }}</span>
                    @else
                        <span class="an-card-ic {{ $cat['tone'] }}" aria-hidden="true">
                            @include('partials.nav-icon', ['icon' => $cat['icon']])
                        </span>
                    @endif

                    <div class="an-card-head">
                        <span class="an-chip {{ $cat['tone'] }}">{{ $cat['label'] }}</span>
                    </div>

                    <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                </div>

                <div class="prose an-full-body">
                    <p>{{ $announcement['body'] }}</p>
                </div>
            </article>
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd"><strong>Details</strong></div>

                <div>
                    @if ($isMilestone)
                        <div class="stat-row">
                            <span class="stat-label">Who</span>
                            <span class="stat-value">{{ $announcement['employee_record']['name'] }}</span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Kind</span>
                            <span class="stat-value">{{ $announcement['milestone_kind'] === 'birthday' ? 'Birthday' : 'Work anniversary' }}</span>
                        </div>
                        <div class="stat-row stat-row-block">
                            <span class="stat-label">Where this came from</span>
                            {{-- Not a written post, and saying so stops somebody
                                 going looking for it in the managing list. --}}
                            <span class="stat-value stat-value-quiet">
                                Computed from the employee record, not written by anyone. It is on the board for today only.
                            </span>
                        </div>
                    @else
                        <div class="stat-row">
                            <span class="stat-label">Reference</span>
                            <span class="stat-value stat-value-mono">{{ $announcement['id'] }}</span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Audience</span>
                            <span class="stat-value">{{ P::audienceLabel($announcement) }}</span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Published</span>
                            <span class="stat-value">{{ P::date($announcement['published_at']) }}</span>
                        </div>
                        <div class="stat-row">
                            <span class="stat-label">Runs</span>
                            <span class="stat-value">{{ $runs['label'] }}</span>
                        </div>
                        <div class="stat-row stat-row-block">
                            <span class="stat-label">What it means</span>
                            <span class="stat-value stat-value-quiet">{{ $pill['meaning'] }}</span>
                        </div>
                    @endif
                </div>
            </section>
        </aside>
    </section>
@endsection
