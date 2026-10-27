{{-- Inline styles and tables: see mail/layout.blade.php. --}}
@extends('mail.layout')

@section('preheader', 'Confirm the change to your sign-in email address.')

@section('heading', 'Confirm your new email address')

@section('content')
    <p style="margin: 0 0 16px;">Hello {{ $name }},</p>

    @if ($half === 'old')
        {{--
            The security half. Written for somebody who may not have asked for
            this, because if the account has been borrowed, this mail is the
            only thing that reaches its real owner.
        --}}
        <p style="margin: 0 0 20px;">
            Somebody asked to change the address you sign in to
            {{ config('zephryx.brand.name') }} {{ config('zephryx.brand.suffix') }} with.
        </p>
    @else
        <p style="margin: 0 0 20px;">
            This address has been given as the new sign-in address for a
            {{ config('zephryx.brand.name') }} {{ config('zephryx.brand.suffix') }} account.
        </p>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 20px;">
        <tr>
            <td style="padding: 14px 18px; background-color: #F2F7F4; border: 1px solid #D6E6DC; border-radius: 10px; font-size: 14px; line-height: 1.8;">
                <span style="color: #5C6B65;">From</span>&nbsp; <strong>{{ $fromEmail }}</strong><br>
                <span style="color: #5C6B65;">To</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <strong>{{ $toEmail }}</strong>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 20px;">
        @if ($half === 'old')
            If that was you, confirm it below. The new address has to confirm as well,
            and nothing changes until both have.
        @else
            Confirm below. The old address has to confirm as well, and nothing changes
            until both have.
        @endif
    </p>

    @include('mail.partials.button', ['url' => $url, 'label' => 'Confirm this change'])

    <p style="margin: 0 0 16px; color: #5C6B65;">
        The link expires in {{ $hours }} hours and works once.
    </p>

    @if ($half === 'old')
        {{--
            The one instruction that matters on this mail, and it is deliberately
            not "click here to cancel": a cancel link in an email is another
            thing an attacker can send. Doing nothing genuinely is enough.
        --}}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td style="padding: 14px 16px; background-color: #FFF8EC; border-left: 3px solid #D98E04; border-radius: 6px;
                           font-size: 13px; line-height: 1.6; color: #5A4310;">
                    <strong>Didn't ask for this?</strong> Do nothing. The change cannot go through
                    without this link, and it expires on its own. Then change your password and
                    tell the owner.
                    @if ($ip)
                        The request came from {{ $ip }}.
                    @endif
                </td>
            </tr>
        </table>
    @else
        <p style="margin: 0; color: #5C6B65;">
            If you were not expecting this, ignore it. Nothing about this address
            changes unless the link is opened.
        </p>
    @endif

    @include('mail.partials.fallback-link', ['url' => $url])
@endsection
