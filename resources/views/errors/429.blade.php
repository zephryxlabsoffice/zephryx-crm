@extends('errors.layout')

{{--
    Reachable today: sign-in is throttled at 10/min and password reset at 5/min
    (§4.2). Someone mistyping a password hits this, so it reads as a pause
    rather than an accusation.
--}}
@section('title', 'Too many attempts')
@section('code', '429')
@section('tone', 'tone-warn')

@section('mark')
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
        <path d="M12 9v4M12 17h.01"/>
    </svg>
@endsection

@section('heading', 'Slow down for a moment')

@section('message')
    That was a lot of requests in a short time, so we have paused them briefly.
    Wait a minute and try again.
@endsection

@section('actions')
    <a class="btn btn-primary" href="{{ url('/dashboard') }}">Go to dashboard</a>
@endsection
