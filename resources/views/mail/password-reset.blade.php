{{-- Inline styles and tables: see mail/layout.blade.php. --}}
@extends('mail.layout')

@section('preheader', 'Choose a new password. The link expires in '.$minutes.' minutes.')

@section('heading', 'Reset your password')

@section('content')
    <p style="margin: 0 0 16px;">Hello {{ $name }},</p>

    <p style="margin: 0 0 20px;">
        We received a request to reset the password on your {{ config('zephryx.brand.name') }}
        {{ config('zephryx.brand.suffix') }} account. Use the button below to choose a new one.
    </p>

    @include('mail.partials.button', ['url' => $url, 'label' => 'Choose a new password'])

    <p style="margin: 0 0 16px; color: #5C6B65;">
        The link expires in {{ $minutes }} minutes and works once.
    </p>

    {{--
        Said explicitly, because a reset email is the one somebody is most
        likely to receive without having asked - and doing nothing really is
        the right response. A link nobody clicks expires on its own.
    --}}
    <p style="margin: 0; color: #5C6B65;">
        If you did not ask for this, you can ignore this email. Your password will
        not change unless somebody opens that link.
        @if ($ip)
            The request came from {{ $ip }}.
        @endif
    </p>

    @include('mail.partials.fallback-link', ['url' => $url])

    @include('mail.partials.not-me')
@endsection
