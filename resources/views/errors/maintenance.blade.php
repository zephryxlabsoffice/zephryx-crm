@extends('errors.layout')

{{-- Shown while maintenance is on from Admin → Maintenance. --}}
@section('title', 'Under maintenance')
@section('code', '503')
@section('tone', 'tone-accent')

@section('mark')
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
    </svg>
@endsection

@section('heading', 'We are doing some maintenance')

@section('message')
    The site cannot be used while we work on it.
    @if ($until)
        It should be back by {{ \Illuminate\Support\Carbon::parse($until)->format('D j M, g:i A') }} (IST).
    @else
        We will email you as soon as it is back.
    @endif
    @if ($note)
        {{ $note }}
    @endif
@endsection

@section('actions')
    <a class="btn btn-primary" href="{{ url('/') }}">Try again</a>
    @if ($signedIn)
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-outline" type="submit">Sign out</button>
        </form>
    @endif
@endsection
