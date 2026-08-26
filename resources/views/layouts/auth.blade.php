@php use App\Support\Theme; @endphp
<!DOCTYPE html>
<html lang="en" data-theme="{{ Theme::forRequest(request()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Sign in') · {{ config('zephryx.brand.name') }} {{ config('zephryx.brand.suffix') }}</title>

    <link rel="icon" href="{{ asset('assets/brand/z-black.svg') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('assets/brand/z-white.svg') }}" media="(prefers-color-scheme: dark)">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
    <a class="skip-link" href="#auth-form">Skip to the form</a>

    <main class="auth">
        @include('auth.partials.visual-panel')

        <section class="form-panel">
            <div class="theme-toggle-slot">
                @include('partials.theme-toggle')
            </div>

            @yield('form')
        </section>
    </main>
</body>
</html>
