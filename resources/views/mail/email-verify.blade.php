{{-- Inline styles and tables: see mail/layout.blade.php. --}}
@extends('mail.layout')

@section('preheader', 'Confirm that '.$address.' is yours.')

@section('heading', 'Verify your email')

@section('content')
    <p style="margin: 0 0 16px;">Hello {{ $name }},</p>

    <p style="margin: 0 0 20px;">
        {{ $address }} was added to your {{ config('zephryx.brand.name') }} account and
        can be used to sign in. Confirm it is yours:
    </p>

    @include('mail.partials.button', ['url' => $url, 'label' => 'Verify this email'])

    <p style="margin: 0 0 16px; color: #5C6B65;">
        The link works for {{ $hours }} hours. If it has expired, ask for a new one from
        My Profile.
    </p>

    <p style="margin: 0; color: #5C6B65;">
        If you did not expect this, tell HR before using the link.
    </p>

    @include('mail.partials.fallback-link', ['url' => $url])
@endsection
