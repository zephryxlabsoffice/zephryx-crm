@extends('errors.layout')

@section('title', 'Not allowed')
@section('code', '403')
@section('tone', 'tone-warn')

@section('mark')
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="11" width="18" height="11" rx="2"/>
        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
    </svg>
@endsection

@section('heading', 'You do not have access to this')

{{--
    Deliberately generic. It does not name the permission, the role that would
    grant it, or whether the record exists — all three tell someone probing the
    application how it is put together, and §5 keeps that knowledge in the Admin
    Panel. "Ask an administrator" is the only useful next step anyway.
--}}
@section('message')
    Your account does not have permission to open this page. If you think it
    should, ask an administrator to check your access.
@endsection

@section('actions')
    <a class="btn btn-primary" href="{{ url('/dashboard') }}">Go to dashboard</a>
    {{-- "an administrator", not "support": the person who can grant access is
         the one holding the Admin Panel, and saying so points them at the
         right door even though the address is the same. --}}
    <a class="btn btn-outline" href="{{ \App\Support\SupportContact::mailto('ZephryxLabs CRM — access request') }}">Contact an administrator</a>
@endsection
