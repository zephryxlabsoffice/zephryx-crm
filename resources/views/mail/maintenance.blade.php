{{-- Inline styles and tables: see mail/layout.blade.php. --}}
@extends('mail.layout')

@php
    $brand = config('zephryx.brand.name');
    $lines = match ($kind) {
        'started' => [
            'heading' => $brand.' is under maintenance',
            'preheader' => 'The site is closed while we work on it. We will email you when it is back.',
            'body' => 'We are doing maintenance work on '.$brand.' right now, so the site cannot be used until it is finished. We will email you again as soon as it is back.',
        ],
        'scheduled' => [
            'heading' => 'Planned maintenance',
            'preheader' => $brand.' will be closed from '.$from.' to '.$to.'.',
            'body' => $brand.' will be closed for maintenance during the time below. Please save your work and sign out before it starts; the site cannot be used until it ends.',
        ],
        'ended' => [
            'heading' => $brand.' is back',
            'preheader' => 'Maintenance is finished and the site is open again.',
            'body' => 'The maintenance work is finished and '.$brand.' is open again. Thank you for your patience.',
        ],
        default => [
            'heading' => 'Maintenance called off',
            'preheader' => 'The planned maintenance will not happen.',
            'body' => 'The planned maintenance has been called off. '.$brand.' stays open as usual.',
        ],
    };
@endphp

@section('preheader', $lines['preheader'])

@section('heading', $lines['heading'])

@section('content')
    <p style="margin: 0 0 16px;">Hello,</p>

    <p style="margin: 0 0 20px;">{{ $lines['body'] }}</p>

    @if ($kind === 'scheduled')
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
            <tr>
                <td style="padding: 14px 18px; background-color: #F2F7F4; border: 1px solid #D6E6DC; border-radius: 10px; font-size: 14px;">
                    <span style="color: #5C6B65;">From</span><br>
                    <strong style="font-size: 16px;">{{ $from }}</strong><br><br>
                    <span style="color: #5C6B65;">To</span><br>
                    <strong style="font-size: 16px;">{{ $to }}</strong>
                    <br><span style="font-size: 12px; color: #7A8A83;">Office time (IST).</span>
                </td>
            </tr>
        </table>
    @endif

    @if ($note)
        <p style="margin: 0 0 20px; color: #5C6B65;">{{ $note }}</p>
    @endif

    @if ($kind === 'ended')
        @include('mail.partials.button', ['url' => $url, 'label' => 'Open '.$brand])
    @endif
@endsection
