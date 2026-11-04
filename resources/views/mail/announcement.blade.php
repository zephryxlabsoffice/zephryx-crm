{{-- Inline styles and tables: see mail/layout.blade.php. --}}
@extends('mail.layout')

@section('preheader', $title)

@section('heading', $title)

@section('content')
    <p style="margin: 0 0 20px; white-space: pre-line;">{{ $body }}</p>

    @include('mail.partials.button', ['url' => $url, 'label' => 'Open in the CRM'])

    <p style="margin: 0; color: #5C6B65;">
        Posted by {{ $author }} on {{ config('zephryx.brand.name') }} {{ config('zephryx.brand.suffix') }}.
    </p>
@endsection
