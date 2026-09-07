{{-- Inline styles: see the note at the head of mail/one-time-code.blade.php. --}}
<div style="font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0F2A1A; line-height: 1.6;">

    <p style="margin: 0 0 20px;">Hello {{ $name }},</p>

    <p style="margin: 0 0 20px;">
        Somebody asked to reset the password on your {{ config('zephryx.brand.name') }}
        {{ config('zephryx.brand.suffix') }} account. Use the link below to choose a new one.
    </p>

    <p style="margin: 0 0 24px;">
        <a href="{{ $url }}"
           style="display: inline-block; background: #0E7A36; color: #ffffff; text-decoration: none;
                  padding: 13px 26px; border-radius: 10px; font-weight: 700;">Choose a new password</a>
    </p>

    <p style="margin: 0 0 20px; color: #5C6B65;">
        The link expires in {{ $minutes }} minutes and works once.
    </p>

    {{--
        Said explicitly, because a reset email is the one somebody is most
        likely to receive without having asked — and doing nothing really is
        the right response. A link nobody clicks expires on its own.
    --}}
    <p style="margin: 0 0 20px; color: #5C6B65;">
        If you did not ask for this, you can ignore this email. Your password will
        not change unless somebody opens that link.
        @if ($ip)
            The request came from {{ $ip }}.
        @endif
    </p>

    <p style="margin: 0; color: #5C6B65; word-break: break-all; font-size: 13px;">
        If the button does not work, paste this into your browser:<br>{{ $url }}
    </p>
</div>
