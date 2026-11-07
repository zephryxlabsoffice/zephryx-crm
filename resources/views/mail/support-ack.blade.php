{{-- Inline styles and tables: see mail/layout.blade.php. The owner's wording (2026-11-06). --}}
@extends('mail.layout')

@if ($known)
    @section('preheader', 'Ticket created. Reference '.$reference.'.')

    @section('heading', 'Ticket created')

    @section('content')
        <p style="margin: 0 0 12px;">Ticket created. Reference number is <strong>{{ $reference }}</strong>.</p>

        <p style="margin: 0 0 20px;">You can check it in {{ $site }} under Tickets. Reply to this email to add to it.</p>

        @include('mail.partials.button', ['url' => $url, 'label' => 'Open the ticket'])
    @endsection
@else
    @section('preheader', 'This is an automated reply. We will contact you shortly.')

    @section('heading', 'We have your message')

    @section('content')
        <p style="margin: 0 0 12px;">This is an automated reply. We will contact you shortly.</p>

        <p style="margin: 0 0 12px;">Kindly contact us using your registered email for quick resolution.</p>

        <p style="margin: 0;">If in a hurry, call us.</p>
    @endsection
@endif
