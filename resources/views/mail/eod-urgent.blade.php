{{-- Inline styles and tables: see mail/layout.blade.php. --}}
@extends('mail.layout')

@section('preheader', $requesterName.' needs your End of Day for '.$reportDay.'.')

@section('heading', 'Your End of Day is needed')

@section('content')
    <p style="margin: 0 0 16px;">Hello {{ $name }},</p>

    <p style="margin: 0 0 20px;">
        {{ $requesterName }} marked your End of Day for <strong>{{ $reportDay }}</strong>
        as urgent. Please file it as soon as you can.
    </p>

    @include('mail.partials.button', ['url' => $url, 'label' => 'File it now'])
@endsection
