@php use App\Support\Theme; @endphp
<!DOCTYPE html>
{{--
    The authenticated shell. Every internal module renders inside this.

    `data-theme`, `data-sidebar` and `data-density` are all resolved
    server-side, so the first paint is already correct — no flash of the wrong
    palette, and no collapsed-then-expanded jump on every navigation.
--}}
<html lang="en"
      data-theme="{{ Theme::forRequest(request()) }}"
      data-sidebar="{{ $sidebarState }}"
      data-density="{{ $density }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Dashboard') · {{ config('zephryx.brand.name') }} {{ config('zephryx.brand.suffix') }}</title>

    <link rel="icon" href="{{ asset('assets/brand/z-black.svg') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('assets/brand/z-white.svg') }}" media="(prefers-color-scheme: dark)">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-body"
      data-sidebar="{{ $sidebarState }}"
      data-mobile-nav="closed">

    <a class="skip-link" href="#page">Skip to content</a>

    <div class="app">
        @include('partials.sidebar')

        <div class="main">
            @include('partials.topbar')

            <main class="page" id="page">
                @hasSection('page-heading')
                    <div class="page-hd">
                        <h1>@yield('page-heading')</h1>
                        @hasSection('page-subheading')
                            <p>@yield('page-subheading')</p>
                        @endif
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    {{-- Closes the mobile drawer on tap. Rendered always so the CSS transition
         has something to animate rather than being created on first open. --}}
    <div class="mobile-scrim" data-mobile-scrim></div>
</body>
</html>
