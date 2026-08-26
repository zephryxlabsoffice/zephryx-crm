<header class="topbar">
    <button class="tb-menu" type="button" data-sidebar-toggle aria-controls="sidebar" aria-expanded="false">
        <span class="sr-only">Toggle navigation</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
            <line x1="3" y1="6" x2="21" y2="6"/>
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
    </button>

    {{-- TODO (backend phase): cross-module search. It has to run every result
         through the permission and ownership checks in §6 before returning
         anything, or it becomes the easiest data-leak surface in the app.
         Disabled rather than hidden so the topbar keeps its designed balance
         and nobody mistakes it for something that works. --}}
    <div class="tb-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/>
            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <label class="sr-only" for="tb-search-input">Search</label>
        <input id="tb-search-input" type="search" placeholder="Search — coming soon" disabled>
    </div>

    <div class="tb-right">
        @include('partials.notifications')

        @include('partials.theme-toggle')

        <div class="tb-user">
            <div class="tb-avatar" aria-hidden="true">{{ $userInitials }}</div>
            <div class="tb-user-info">
                <strong>{{ $userName }}</strong>
                <span>{{ $userRole }}</span>
            </div>
        </div>
    </div>
</header>
