{{-- Inline styles and tables: see mail/layout.blade.php. --}}
@extends('mail.layout')

@section('preheader', 'Your '.config('zephryx.brand.name').' client portal account for '.$company.' is ready.')

@section('heading', 'Welcome to the client portal')

@section('content')
    <p style="margin: 0 0 16px;">Hello {{ $name }},</p>

    <p style="margin: 0 0 16px;">
        {{ $addedBy }} at {{ config('zephryx.brand.name') }} has set up a client portal
        account for <strong>{{ $company }}</strong>. In the portal you can:
    </p>

    <ul style="margin: 0 0 20px; padding-left: 20px;">
        <li style="margin-bottom: 6px;">follow your projects and their progress,</li>
        <li style="margin-bottom: 6px;">see and download your invoices,</li>
        <li style="margin-bottom: 6px;">raise a support ticket and follow its replies,</li>
        <li style="margin-bottom: 6px;">request a meeting with our team,</li>
        <li>read announcements we share with you.</li>
    </ul>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
        <tr>
            <td style="padding: 14px 18px; background-color: #F2F7F4; border: 1px solid #D6E6DC; border-radius: 10px; font-size: 14px;">
                <span style="color: #5C6B65;">Your client ID</span><br>
                <strong style="font-size: 17px; letter-spacing: 1px;">{{ $clientId }}</strong>
                <br><span style="font-size: 12px; color: #7A8A83;">You can sign in with this or with your email address.</span>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 8px;">To get started, choose your password:</p>

    @include('mail.partials.button', ['url' => $url, 'label' => 'Choose your password'])

    <p style="margin: 0 0 16px; color: #5C6B65;">
        The link expires in {{ $minutes }} minutes and works once. If it has expired, go to
        {{ $portal }} and use "Forgot password" to get a fresh one.
    </p>

    <p style="margin: 0; color: #5C6B65;">
        If you were not expecting this, reply to let us know before using the link.
    </p>

    @include('mail.partials.fallback-link', ['url' => $url])
@endsection
