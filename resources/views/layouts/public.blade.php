@php use App\Support\Theme; @endphp
<!DOCTYPE html>
{{--
    `data-theme` is resolved server-side (spec §7) so the first paint is already
    correct. That removes the need for a render-blocking inline script, which in
    turn lets the Content-Security-Policy stay free of 'unsafe-inline'.
--}}
<html lang="en" data-theme="{{ Theme::forRequest(request()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="@yield('description', 'The ZephryxLabs workspace.')">

    <title>@yield('title', config('zephryx.brand.name').' '.config('zephryx.brand.suffix'))</title>

    <link rel="icon" href="{{ asset('assets/brand/z-black.svg') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('assets/brand/z-white.svg') }}" media="(prefers-color-scheme: dark)">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="@yield('body-class')">
    <a class="skip-link" href="#main">Skip to content</a>

    <div class="container">
        <header class="topbar">
            <a class="brand" href="{{ route('landing') }}" aria-label="{{ config('zephryx.brand.name') }} {{ config('zephryx.brand.suffix') }} home">
                <img class="brand-mark brand-mark-light" src="{{ asset('assets/brand/z-black.svg') }}" alt="" width="42" height="42">
                <img class="brand-mark brand-mark-dark" src="{{ asset('assets/brand/z-white.svg') }}" alt="" width="42" height="42">
                <span class="brand-name">{{ config('zephryx.brand.name') }}<em>{{ config('zephryx.brand.suffix') }}</em></span>
            </a>

            @include('partials.theme-toggle')
        </header>

        <main id="main">
            @yield('content')
        </main>
    </div>
</body>
</html>
