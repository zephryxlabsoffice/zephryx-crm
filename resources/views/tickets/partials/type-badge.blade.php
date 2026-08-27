{{--
    Internal or client. This is not decoration: it decides who may read the
    ticket at all, so it carries a word and an icon, never colour alone.
--}}
@if ($type === 'client')
    <span class="tkt-type is-client">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
        </svg>
        Client
    </span>
@else
    <span class="tkt-type is-internal">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 21V8l9-5 9 5v13"/><path d="M9 21v-6h6v6"/>
        </svg>
        Internal
    </span>
@endif
