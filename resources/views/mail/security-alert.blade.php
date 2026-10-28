{{-- Inline styles and tables: see mail/layout.blade.php. --}}
@extends('mail.layout')

@section('preheader', $name.'\'s account was put on hold after "This wasn\'t me" was pressed.')

@section('heading', 'An account is on hold')

@section('content')
    <p style="margin: 0 0 16px;">Hello,</p>

    <p style="margin: 0 0 20px;">
        Somebody using <strong>{{ $address }}</strong> pressed "This wasn't me" on
        {{ $what }} email for <strong>{{ $name }}</strong> ({{ $staffId }}). The account
        is on hold for security: nobody can sign in, and it was signed out everywhere.
    </p>

    <p style="margin: 0 0 20px;">
        Check with {{ $name }} in person. When it is safe, open the account and press
        Restore: they will be emailed a link to choose a new password.
    </p>

    @include('mail.partials.button', ['url' => $url, 'label' => 'Open the account'])

    @include('mail.partials.fallback-link', ['url' => $url])
@endsection
