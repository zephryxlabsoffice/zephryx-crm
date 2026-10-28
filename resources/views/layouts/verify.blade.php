@php use App\Support\Theme; @endphp
<!DOCTYPE html>
{{--
    verify.zephryxlabs.in (2026-10-29). Its own frame: no CRM sidebar, no
    viewer data. noindex, because these pages carry people's names.
--}}
<html lang="en" data-theme="{{ Theme::forRequest(request()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title') · {{ config('zephryx.brand.name') }} Verify</title>

    <link rel="icon" href="{{ asset('assets/brand/z-black.svg') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('assets/brand/z-white.svg') }}" media="(prefers-color-scheme: dark)">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="vf-body">
    <div class="vf">
        <header class="vf-top">
            <a class="brand" href="{{ route('verify.landing') }}" aria-label="{{ config('zephryx.brand.name') }} Verify home">
                @include('partials.brand-mark', ['alt' => ''])
                <span class="brand-name">{{ config('zephryx.brand.name') }}<em>Verify</em></span>
            </a>

            @include('partials.theme-toggle')
        </header>

        <main class="vf-main @yield('main-class')">
            @if (session('status'))
                @include('partials.notice', [
                    'tone' => session('status_tone', 'success'),
                    'message' => session('status'),
                ])
            @endif

            @yield('content')
        </main>

        <footer class="vf-foot">
            {{ config('zephryx.brand.name') }} · Employee verification
        </footer>
    </div>
</body>
</html>
