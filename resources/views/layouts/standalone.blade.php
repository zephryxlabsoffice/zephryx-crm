@php use App\Support\Theme; @endphp
<!DOCTYPE html>
{{--
    A centred, self-contained page: brand, a card, nothing else.

    For the surfaces that are neither the app shell nor the sign-in form — the
    end of a journey rather than a step in one. Like the error layout it pulls
    in no navigation, no view composer and no viewer data, because the pages
    that use it render for people the application has decided not to let in.

    `data-theme` still resolves server-side. A page that flashes the wrong
    palette on the way to telling somebody their account is closed is a second
    small failure on top of the first.
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
    <div class="standalone">
        <header class="standalone-top">
            <a class="brand" href="{{ url('/') }}" aria-label="{{ config('zephryx.brand.name') }} home">
                @include('partials.brand-mark', ['alt' => ''])
                <span class="brand-name">{{ config('zephryx.brand.name') }}<em>{{ config('zephryx.brand.suffix') }}</em></span>
            </a>

            @include('partials.theme-toggle')
        </header>

        <main class="standalone-body">
            @yield('content')
        </main>
    </div>
</body>
</html>
