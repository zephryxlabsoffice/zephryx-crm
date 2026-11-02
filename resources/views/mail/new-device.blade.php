{{-- Inline styles and tables: see mail/layout.blade.php. --}}
@extends('mail.layout')

@section('preheader', $deviceName.' signed in to your account. '.$location.'.')

@section('heading', 'New device signed in')

@section('content')
    <p style="margin: 0 0 16px;">Hello {{ $name }},</p>

    <p style="margin: 0 0 16px;">
        Your {{ config('zephryx.brand.name') }} {{ config('zephryx.brand.suffix') }} account
        was just signed in on a device it has not used before.
    </p>

    <p style="margin: 0 0 20px;">
        <strong>Device:</strong> {{ $deviceName }}<br>
        <strong>Location:</strong> {{ $location }}<br>
        @if ($ip)
            <strong>IP address:</strong> {{ $ip }}<br>
        @endif
        <strong>Time:</strong> {{ $signedInAt }}
    </p>

    <p style="margin: 0 0 16px; color: #5C6B65;">
        If this was you, there is nothing to do. The location is an estimate and can
        be off by a city or more. You can see everywhere you are signed in on
        <a href="{{ $devicesUrl }}" target="_blank" style="color: #5C6B65;">My Devices</a>.
    </p>

    <p style="margin: 0 0 16px; color: #5C6B65;">
        If this was not you, press the button below: your account will be locked and
        signed out everywhere, and the administrators will be told straight away.
    </p>

    @include('mail.partials.alarm-button', ['url' => $notMeUrl])
@endsection
