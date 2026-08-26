@php use App\Support\Theme; @endphp
@php
    $current = Theme::forRequest(request());
    $next = $current === 'dark' ? 'light' : 'dark';
@endphp

{{--
    A real form, so the toggle works without JavaScript. resources/js/theme.js
    upgrades it to an in-place swap; the POST is CSRF-protected either way.
--}}
<form method="POST" action="{{ route('theme.store') }}" data-theme-form>
    @csrf
    <input type="hidden" name="theme" value="{{ $next }}">

    <button type="submit" class="theme-toggle" title="Switch to {{ $next }} mode">
        <span class="sr-only">Switch to {{ $next }} mode</span>

        {{-- Shown while dark is active: the button switches to light. --}}
        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="4"/>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
        </svg>

        {{-- Shown while light is active: the button switches to dark. --}}
        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z"/>
        </svg>
    </button>
</form>
