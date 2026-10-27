{{--
    The sign-in code. Inline styles and tables: see mail/layout.blade.php.

    The code is in the body only — never the subject or the preheader, both of
    which show in notification previews on a locked screen.
--}}
@extends('mail.layout')

@section('preheader', 'Your sign-in code, valid for '.$minutes.' minutes.')

@section('heading', 'Your sign-in code')

@section('content')
    <p style="margin: 0 0 16px;">Hello {{ $name }},</p>

    <p style="margin: 0 0 20px;">
        Use this code to finish signing in to {{ config('zephryx.brand.name') }} {{ config('zephryx.brand.suffix') }}.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 20px;">
        <tr>
            <td align="center" style="padding: 22px 16px; background-color: #F2F7F4; border: 1px solid #D6E6DC; border-radius: 12px;
                                      font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace; font-size: 34px; font-weight: 700;
                                      letter-spacing: 10px; color: #0F2A1A;">
                {{ $code }}
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 24px; color: #5C6B65;">
        It expires in {{ $minutes }} minutes and can be used once.
    </p>

    {{--
        The sentence that makes an unexpected code useful rather than confusing.
        Somebody who did not ask for this now knows their password is known to
        somebody else, and what to do about it.
    --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="padding: 14px 16px; background-color: #FFF8EC; border-left: 3px solid #D98E04; border-radius: 6px;
                       font-size: 13px; line-height: 1.6; color: #5A4310;">
                <strong>Didn't try to sign in?</strong> Somebody else has your password. Change it as
                soon as you can and tell your administrator.
                @if ($ip)
                    The attempt came from {{ $ip }}.
                @endif
            </td>
        </tr>
    </table>

    <p style="margin: 20px 0 0; font-size: 13px; color: #7A8A83;">
        Nobody from {{ config('zephryx.brand.name') }} will ever phone or email to ask you for this code.
    </p>
@endsection
