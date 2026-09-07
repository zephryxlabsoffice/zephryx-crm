@extends('errors.layout')

@php
    use App\Support\Navigation\Navigation;

    // Leads, Calendar and Reports keep their navigation entries and 404 until
    // v2 (§12). Someone who clicked a link we chose to show them deserves to be
    // told it is not built yet, not that it does not exist.
    $deferred = Navigation::deferredEntryFor(request()->path());
@endphp

@section('title', $deferred ? $deferred['label'] : 'Page not found')
@section('code', '404')

@if ($deferred)
    @section('tone', 'tone-accent')

    @section('mark')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
    @endsection

    @section('heading', $deferred['label'].' is not built yet')

    @section('message')
        This module is planned for a later version. Its place in the navigation is
        already reserved, so nothing will move around when it arrives.
    @endsection
@else
    @section('mark')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            <line x1="8.5" y1="11" x2="13.5" y2="11"/>
        </svg>
    @endsection

    @section('heading', 'We cannot find that page')

    @section('message')
        The link may be out of date, or the page may have been moved. Check the
        address, or head back and try again from the navigation.
    @endsection

    {{-- The path is echoed as escaped text inside a <code>, never used to build
         a link or a message — a 404 page is a classic place to reflect
         attacker-controlled input straight back at the browser. --}}
    @section('meta')
        You asked for <code>/{{ request()->path() }}</code>
    @endsection
@endif
