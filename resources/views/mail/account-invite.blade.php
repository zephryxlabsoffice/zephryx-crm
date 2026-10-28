{{-- Inline styles and tables: see mail/layout.blade.php. --}}
@extends('mail.layout')

@section('preheader', $addedBy.' has set up your account. Choose a password to get started.')

@section('heading', 'Welcome to '.config('zephryx.brand.name'))

@section('content')
    <p style="margin: 0 0 16px;">Hello {{ $name }},</p>

    <p style="margin: 0 0 20px;">
        {{ $addedBy }} has set up your {{ config('zephryx.brand.name') }}
        {{ config('zephryx.brand.suffix') }} account. Choose a password to sign in
        for the first time.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
        <tr>
            <td style="padding: 14px 18px; background-color: #F2F7F4; border: 1px solid #D6E6DC; border-radius: 10px; font-size: 14px;">
                <span style="color: #5C6B65;">Your staff ID</span><br>
                <strong style="font-size: 17px; letter-spacing: 1px;">{{ $staffId }}</strong>
                <br><span style="font-size: 12px; color: #7A8A83;">You sign in with your email address; the staff ID is what appears on your records.</span>
            </td>
        </tr>
    </table>

    @include('mail.partials.button', ['url' => $url, 'label' => 'Choose your password'])

    <p style="margin: 0 0 16px; color: #5C6B65;">
        The link expires in {{ $minutes }} minutes and works once. If it has expired
        by the time you open it, use "Forgot password" on the sign-in page to get a
        fresh one. Your account is already set up.
    </p>

    {{--
        Who added them, by name. An account appearing unannounced is worth being
        able to check with a person rather than with an address nobody
        recognises - and if it really was unexpected, the named person is who
        can say so.
    --}}
    <p style="margin: 0; color: #5C6B65;">
        If you were not expecting this, speak to {{ $addedBy }} before using the link.
    </p>

    @include('mail.partials.fallback-link', ['url' => $url])
@endsection
