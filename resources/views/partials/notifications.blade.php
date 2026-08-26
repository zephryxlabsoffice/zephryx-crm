{{--
    Notification bell and panel.

    Server-rendered from the `notifications` table (foundation spec §8). The
    handover populated this by cloning the host page's "Recent Activity" list
    in JavaScript — that was a design-time trick: it made the panel a mirror of
    whatever happened to be on screen rather than of the viewer's own unread
    notifications, and it showed nothing at all on pages with no activity list.
--}}
@php
    $unread = collect($notifications)->where('read_at', null)->count();
@endphp

<div class="notif-wrap" data-notifications data-open="false">
    <button class="tb-bell" type="button" data-notif-toggle aria-expanded="false" aria-haspopup="true">
        <span class="sr-only">
            Notifications{{ $unread > 0 ? ' — '.$unread.' unread' : '' }}
        </span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>

        {{-- The badge is absent, not zero: a "0" badge is visual noise that
             trains people to ignore the thing that matters. --}}
        @if ($unread > 0)
            <span class="badge" aria-hidden="true">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </button>

    <div class="notif-pop" data-notif-panel role="dialog" aria-label="Notifications">
        <div class="notif-hd">
            <strong>Notifications</strong>
            @if ($unread > 0)
                <span class="notif-count">{{ $unread }} new</span>
            @endif
        </div>

        <div class="notif-list">
            @forelse ($notifications as $notification)
                <a class="notif-item @if (! $notification['read_at']) is-unread @endif"
                   href="{{ $notification['link'] ?? '#' }}">
                    <span class="notif-ic" aria-hidden="true">
                        @include('partials.nav-icon', ['icon' => $notification['icon'] ?? 'announcements'])
                    </span>
                    <span class="notif-body">
                        <strong>{{ $notification['title'] }}</strong>
                        <span>{{ $notification['body'] }}</span>
                    </span>
                    <span class="notif-time">{{ $notification['when'] }}</span>
                </a>
            @empty
                <p class="notif-empty">Nothing new right now.</p>
            @endforelse
        </div>

        @if ($notifications !== [])
            <div class="notif-foot">
                <a href="{{ route('notifications.index') }}">View all activity</a>
            </div>
        @endif
    </div>
</div>
