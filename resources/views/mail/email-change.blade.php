{{-- Inline styles: see the note at the head of mail/one-time-code.blade.php. --}}
<div style="font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0F2A1A; line-height: 1.6;">

    <p style="margin: 0 0 20px;">Hello {{ $name }},</p>

    @if ($half === 'old')
        {{--
            The security half. Written for somebody who may not have asked for
            this, because if the account has been borrowed, this mail is the
            only thing that reaches its real owner.
        --}}
        <p style="margin: 0 0 20px;">
            Somebody asked to change the address you sign in to
            {{ config('zephryx.brand.name') }} {{ config('zephryx.brand.suffix') }} with,
            from <strong>{{ $fromEmail }}</strong> to <strong>{{ $toEmail }}</strong>.
        </p>

        <p style="margin: 0 0 20px;">
            If that was you, confirm it below. The new address has to confirm as well,
            and nothing changes until both have.
        </p>
    @else
        <p style="margin: 0 0 20px;">
            This address has been given as the new sign-in address for the
            {{ config('zephryx.brand.name') }} {{ config('zephryx.brand.suffix') }} account
            that currently signs in as <strong>{{ $fromEmail }}</strong>.
        </p>

        <p style="margin: 0 0 20px;">
            Confirm below. The old address has to confirm as well, and nothing changes
            until both have.
        </p>
    @endif

    <p style="margin: 0 0 24px;">
        <a href="{{ $url }}"
           style="display: inline-block; background: #0E7A36; color: #ffffff; text-decoration: none;
                  padding: 13px 26px; border-radius: 10px; font-weight: 700;">Confirm this change</a>
    </p>

    <p style="margin: 0 0 20px; color: #5C6B65;">
        The link expires in {{ $hours }} hours and works once.
    </p>

    @if ($half === 'old')
        {{--
            The one instruction that matters on this mail, and it is deliberately
            not "click here to cancel": a cancel link in an email is another
            thing an attacker can send. Doing nothing genuinely is enough.
        --}}
        <p style="margin: 0 0 20px; color: #5C6B65;">
            If you did not ask for this, do nothing — the change cannot go through
            without this link, and it expires on its own. Then change your password,
            and tell the owner.
            @if ($ip)
                The request came from {{ $ip }}.
            @endif
        </p>
    @else
        <p style="margin: 0 0 20px; color: #5C6B65;">
            If you were not expecting this, ignore it. Nothing about this address
            changes unless the link is opened.
        </p>
    @endif

    <p style="margin: 0; color: #5C6B65; word-break: break-all; font-size: 13px;">
        If the button does not work, paste this into your browser:<br>{{ $url }}
    </p>
</div>
