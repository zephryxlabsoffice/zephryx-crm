{{-- Inline styles: see the note at the head of mail/one-time-code.blade.php. --}}
<div style="font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0F2A1A; line-height: 1.6;">

    <p style="margin: 0 0 20px;">Hello {{ $name }},</p>

    <p style="margin: 0 0 20px;">
        {{ $addedBy }} has set up your {{ config('zephryx.brand.name') }}
        {{ config('zephryx.brand.suffix') }} account. Choose a password to sign in
        for the first time.
    </p>

    <p style="margin: 0 0 20px;">
        Your staff ID is <strong>{{ $staffId }}</strong>. You sign in with your
        email address; the staff ID is what appears on your records.
    </p>

    <p style="margin: 0 0 24px;">
        <a href="{{ $url }}"
           style="display: inline-block; background: #0E7A36; color: #ffffff; text-decoration: none;
                  padding: 13px 26px; border-radius: 10px; font-weight: 700;">Choose your password</a>
    </p>

    <p style="margin: 0 0 20px; color: #5C6B65;">
        The link expires in {{ $minutes }} minutes and works once. If it has expired
        by the time you open it, use "Forgot password" on the sign-in page to get a
        fresh one — the account is already there waiting.
    </p>

    {{--
        Who added them, by name. An account appearing unannounced is worth being
        able to check with a person rather than with an address nobody
        recognises — and if it really was unexpected, the named person is who
        can say so.
    --}}
    <p style="margin: 0 0 20px; color: #5C6B65;">
        If you were not expecting this, speak to {{ $addedBy }} before using the
        link.
    </p>

    <p style="margin: 0; color: #5C6B65; word-break: break-all; font-size: 13px;">
        If the button does not work, paste this into your browser:<br>{{ $url }}
    </p>
</div>
