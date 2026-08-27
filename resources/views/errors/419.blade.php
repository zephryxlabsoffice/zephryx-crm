@extends('errors.layout')

{{--
    Laravel's stock wording here is "Page Expired", which tells someone who has
    just lost a half-written form nothing about what to do. This is the most
    likely error page in the application: every form carries a CSRF token (§6)
    and sessions time out after 12 hours (§4.4), so leaving a tab open
    overnight lands here.
--}}
@section('title', 'Session expired')
@section('code', '419')
@section('tone', 'tone-accent')

@section('mark')
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
    </svg>
@endsection

@section('heading', 'Your session expired')

@section('message')
    You were signed out for security after a period of inactivity, so that form
    was not submitted. Sign in again and it will take you back.
@endsection

@section('actions')
    <a class="btn btn-primary" href="{{ url('/login') }}">Sign in again</a>
@endsection
