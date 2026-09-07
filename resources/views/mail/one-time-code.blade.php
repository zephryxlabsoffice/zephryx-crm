{{--
    The sign-in code.

    Plain HTML with inline styles, and that is correct HERE and nowhere else in
    this application: email clients strip <style> blocks and have no CSS
    variables, so the token system cannot reach them. The CSP that forbids
    inline styles governs the browser, not Outlook.

    Kept deliberately plain for the same reason — a heavily designed email is
    more likely to be mangled, and this one has exactly one job.
--}}
<div style="font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0F2A1A; line-height: 1.6;">

    <p style="margin: 0 0 20px;">Hello {{ $name }},</p>

    <p style="margin: 0 0 20px;">
        Here is your code for signing in to {{ config('zephryx.brand.name') }} {{ config('zephryx.brand.suffix') }}.
    </p>

    <p style="margin: 0 0 24px; font-size: 32px; font-weight: 700; letter-spacing: 8px; font-family: ui-monospace, 'SF Mono', Menlo, monospace;">
        {{ $code }}
    </p>

    <p style="margin: 0 0 20px;">
        It expires in {{ $minutes }} minutes and can be used once.
    </p>

    {{--
        The sentence that makes an unexpected code useful rather than confusing.
        Somebody who did not ask for this now knows their password is known to
        somebody else, and what to do about it.
    --}}
    <p style="margin: 0 0 20px; color: #5C6B65;">
        If you did not try to sign in, somebody else has your password. Change it
        as soon as you can, and tell your administrator.
        @if ($ip)
            The attempt came from {{ $ip }}.
        @endif
    </p>

    <p style="margin: 0; color: #5C6B65;">
        We will never ask you for this code. Nobody from {{ config('zephryx.brand.name') }}
        will phone or email to ask you to read it out.
    </p>
</div>
