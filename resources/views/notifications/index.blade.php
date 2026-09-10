@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Notifications</h1>
            <p>Things addressed to you.</p>
        </div>

        @if ($tabCounts['unread'] > 0)
            <div class="hd-actions">
                <form method="POST" action="{{ route('notifications.read') }}">
                    @csrf
                    {{-- The reader's own act. The form carries no id, because
                         the route works on the signed-in account's own unread
                         rows and nothing else — nobody clears anybody else's,
                         and nothing marks a row read on behalf of somebody who
                         has not seen it. --}}
                    <button class="btn btn-outline" type="submit">
                        Mark all as read
                    </button>
                </form>
            </div>
        @endif
    </div>

    <section class="an-grid">
        <div class="an-feed-col">
            <div class="card table-card">
                <nav class="tabs" aria-label="Notifications">
                    @foreach (['all' => 'All', 'unread' => 'Unread'] as $key => $label)
                        <a class="tab @if ($tab === $key) active @endif"
                           href="{{ route('notifications.index', ['tab' => $key]) }}"
                           @if ($tab === $key) aria-current="page" @endif>
                            {{ $label }}
                            <span class="tab-count">{{ $tabCounts[$key] }}</span>
                        </a>
                    @endforeach
                </nav>

                <ul class="nt-list">
                    @forelse ($notificationList as $item)
                        <li class="nt-item @if (! $item['read_at']) is-unread @endif">
                            <span class="nt-ic" aria-hidden="true">
                                @include('partials.nav-icon', ['icon' => $item['icon']])
                            </span>

                            <span class="nt-body">
                                <strong>
                                    @if ($item['link'])
                                        {{-- A link only when it points somewhere
                                             real. One aimed at a page that does
                                             not exist is a promise the
                                             application cannot keep. --}}
                                        <a href="{{ $item['link'] }}">{{ $item['title'] }}</a>
                                    @else
                                        {{ $item['title'] }}
                                    @endif
                                </strong>
                                <span>{{ $item['body'] }}</span>
                            </span>

                            <span class="nt-meta">
                                <span class="nt-when">{{ $item['when'] }}</span>
                                @if (! $item['read_at'])
                                    <span class="nt-new">New</span>
                                @endif
                            </span>
                        </li>
                    @empty
                        <li>
                            <div class="table-empty">
                                <strong>{{ $tab === 'unread' ? 'Nothing unread.' : 'Nothing yet.' }}</strong>
                                Task assignments, ticket escalations and decisions on your leave appear here.
                            </div>
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd"><strong>Where things appear</strong></div>

                <ul class="an-facts">
                    <li class="an-fact">
                        <span class="an-fact-ic tone-warn" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                            </svg>
                        </span>
                        <span class="an-fact-body">
                            <strong>This page</strong>
                            <span>Only things addressed to you. Nobody else sees your notifications, and you do not see theirs.</span>
                        </span>
                    </li>

                    <li class="an-fact">
                        <span class="an-fact-ic tone-accent" aria-hidden="true">
                            @include('partials.nav-icon', ['icon' => 'announcements'])
                        </span>
                        <span class="an-fact-body">
                            <strong>The board</strong>
                            {{-- One expression: a Blade newline before the full
                                 stop renders as "Go there ." --}}
                            <span>
                                Things everyone should read — policy, holidays, birthdays.
                                {!! '<a class="card-link" href="'.e(route('announcements.index')).'">Go there</a>.' !!}
                            </span>
                        </span>
                    </li>
                </ul>
            </section>
        </aside>
    </section>
@endsection
