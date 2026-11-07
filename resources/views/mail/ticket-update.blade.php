{{-- Inline styles and tables: see mail/layout.blade.php. --}}
@extends('mail.layout')

@section('preheader', $headline.': '.$reference.'.')

@section('heading', $headline)

@section('content')
    <p style="margin: 0 0 12px;"><strong>{{ $reference }}</strong> · {{ $ticketSubject }}</p>

    <p style="margin: 0 0 20px;">{{ $body }}</p>

    @include('mail.partials.button', ['url' => $url, 'label' => 'Open the ticket'])
@endsection
