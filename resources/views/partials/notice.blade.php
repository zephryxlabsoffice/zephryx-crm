{{--
    Form-level feedback banner.

    @include('partials.notice', ['tone' => 'danger', 'title' => '…', 'message' => '…'])

    `tone` is one of danger | warning | info | success. `title` is optional.
--}}
@php
    $tone = in_array($tone ?? 'danger', ['danger', 'warning', 'info', 'success'], true)
        ? $tone
        : 'danger';
@endphp

<div class="notice notice-{{ $tone }}" role="{{ $tone === 'danger' ? 'alert' : 'status' }}">
    <span class="notice-icon" aria-hidden="true">
        @if ($tone === 'danger')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16h.01"/>
            </svg>
        @elseif ($tone === 'warning')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
                <path d="M12 9v4M12 17h.01"/>
            </svg>
        @elseif ($tone === 'success')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/>
            </svg>
        @else
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M12 16v-5M12 8h.01"/>
            </svg>
        @endif
    </span>

    <div class="notice-body">
        @isset($title)
            <strong>{{ $title }}</strong>
        @endisset
        <p>{{ $message }}</p>
    </div>
</div>
