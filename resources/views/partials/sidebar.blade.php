{{--
    Staff sidebar. Entries come from config/navigation.php filtered per viewer
    by App\Support\Navigation\Navigation (foundation spec §5).

    Filtering the nav hides what a person cannot use; it is NOT the access
    control. Every route carries its own guard (§3.1) — this is the courtesy
    layer on top of it.
--}}
<aside class="sidebar" id="sidebar" aria-label="Main navigation">

    <a class="sb-brand" href="{{ $navigation === [] ? url('/') : $navigation[0]['url'] }}">
        @include('partials.brand-mark', ['alt' => ''])
        <span class="brand-text">{{ config('zephryx.brand.name') }}<em>{{ config('zephryx.brand.suffix') }}</em></span>
    </a>

    <nav class="sb-nav">
        @foreach ($navigation as $item)
            {{-- @class rather than an inline @if, so the rendered attribute is
                 exactly "sb-link" or "sb-link active" with no stray space. --}}
            <a @class(['sb-link', 'active' => $item['active']])
               href="{{ $item['url'] }}"
               @if ($item['active']) aria-current="page" @endif>
                <span class="sb-ic" aria-hidden="true">
                    @include('partials.nav-icon', ['icon' => $item['icon']])
                </span>
                <span class="sb-label">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="sb-foot">
        {{-- Sign-out changes state, so it is a POST with a CSRF token. As a GET
             link any <img src="/logout"> on any page could sign people out. --}}
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sb-link">
                <span class="sb-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <path d="M16 17l5-5-5-5"/>
                        <path d="M21 12H9"/>
                    </svg>
                </span>
                <span class="sb-label">Log out</span>
            </button>
        </form>
    </div>
</aside>
