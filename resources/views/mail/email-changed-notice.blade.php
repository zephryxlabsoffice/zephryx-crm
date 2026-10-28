{{-- Inline styles and tables: see mail/layout.blade.php. --}}
@extends('mail.layout')

@section('preheader', 'An email on your account was changed to '.$newAddress.'.')

@section('heading', 'Your email was changed')

@section('content')
    <p style="margin: 0 0 16px;">Hello {{ $name }},</p>

    <p style="margin: 0 0 20px;">
        An email address on your {{ config('zephryx.brand.name') }} account was changed
        from <strong>{{ $oldAddress }}</strong> to <strong>{{ $newAddress }}</strong>.
    </p>

    <p style="margin: 0 0 16px; color: #5C6B65;">
        If you asked for this, there is nothing to do. If you did not, press the button
        below: your account will be locked and signed out everywhere, and the
        administrators will be told straight away.
    </p>

    @include('mail.partials.alarm-button', ['url' => $notMeUrl])
@endsection
