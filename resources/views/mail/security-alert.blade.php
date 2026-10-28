{{-- Inline styles and tables: see mail/layout.blade.php. --}}
@extends('mail.layout')

@section('preheader', $name.'\'s account was locked after "This wasn\'t me" was pressed.')

@section('heading', 'An account was locked')

@section('content')
    <p style="margin: 0 0 16px;">Hello,</p>

    <p style="margin: 0 0 20px;">
        Somebody using <strong>{{ $address }}</strong> pressed "This wasn't me" on an
        email about a change to <strong>{{ $name }}</strong>'s ({{ $staffId }}) email
        address. The account has been locked: it is suspended, and it was signed out
        everywhere.
    </p>

    <p style="margin: 0 0 20px;">
        Check with {{ $name }} in person. If the change was genuine, correct the email if
        needed and set the account back to Active from the Admin Panel. If it was not,
        find out who made the change before unlocking it.
    </p>

    @include('mail.partials.button', ['url' => $url, 'label' => 'Open the account'])

    @include('mail.partials.fallback-link', ['url' => $url])
@endsection
