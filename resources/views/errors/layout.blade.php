@php
    use App\Support\Realm;
    use App\Support\SupportContact;
    use App\Support\Theme;

    /*
     * "Go to dashboard" has to mean the reader's OWN dashboard.
     *
     * It was hardcoded to /dashboard, which is the staff one — so a client who
     * mistyped a URL was offered a button into a realm their session will be
     * refused from, and the way out of an error page was a second error page.
     *
     * Realm::forRequest reads the path, which is exactly right here: this is a
     * link, not an authorisation decision, and the realm middleware still
     * decides what they may open when they get there.
     */
    $home = match (Realm::forRequest(request())) {
        Realm::CLIENT => '/client/dashboard',
        Realm::ADMIN => '/admin/dashboard',
        default => '/dashboard',
    };
@endphp
<!DOCTYPE html>
{{--
    The shell for every error page.

    Deliberately standalone — no sidebar, no topbar, no navigation query, no
    view composer. This is what renders when something has already gone wrong,
    including when the thing that went wrong is the app shell itself, so it
    depends on as little as possible.

    `theme` still resolves server-side, because an error page that flashes the
    wrong palette is a second small failure on top of the first.
--}}
<html lang="en" data-theme="{{ Theme::forRequest(request()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title') · {{ config('zephryx.brand.name') }} {{ config('zephryx.brand.suffix') }}</title>

    <link rel="icon" href="{{ asset('assets/brand/z-black.svg') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('assets/brand/z-white.svg') }}" media="(prefers-color-scheme: dark)">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="error-page">
        <header class="error-top">
            <a class="brand" href="{{ url('/') }}" aria-label="{{ config('zephryx.brand.name') }} home">
                @include('partials.brand-mark', ['alt' => ''])
                <span class="brand-name">{{ config('zephryx.brand.name') }}<em>{{ config('zephryx.brand.suffix') }}</em></span>
            </a>

            @include('partials.theme-toggle')
        </header>

        <main class="error-body">
            <div class="error-card">
                <div class="error-mark @yield('tone')" aria-hidden="true">
                    @yield('mark')
                </div>

                <span class="error-code">Error @yield('code')</span>

                <h1 class="error-title">@yield('heading')</h1>

                <p class="error-message">@yield('message')</p>

                <div class="error-actions">
                    @hasSection('actions')
                        @yield('actions')
                    @else
                        {{-- "Contact Support" rather than "back to start": someone
                             on an error page has already found the thing that did
                             not work, and sending them to the landing page just
                             makes them find it again.

                             The subject carries the status code so a reply does
                             not have to start by asking what they saw. Sections
                             are resolved before the layout renders, so
                             yieldContent is safe here. --}}
                        @php
                            $supportSubject = config('zephryx.brand.name').' '.config('zephryx.brand.suffix')
                                .' — error '.trim($__env->yieldContent('code'));
                        @endphp
                        <a class="btn btn-primary" href="{{ url($home) }}">Go to dashboard</a>
                        <a class="btn btn-outline" href="{{ SupportContact::mailto($supportSubject) }}">Contact Support</a>
                    @endif
                </div>

                @hasSection('meta')
                    <p class="error-meta">@yield('meta')</p>
                @endif
            </div>
        </main>
    </div>
</body>
</html>
