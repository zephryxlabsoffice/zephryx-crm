{{--
    The frame every email sits in: header, white card, footer.

    ─────────────────────────────────────────────────────────────────────────────
    WHY IT IS BUILT LIKE 2005

    Tables, inline styles and a fixed 600px width, because that is what email
    clients render: they strip <style> blocks and have no CSS variables, so the
    token system cannot reach them. The CSP that forbids inline styles governs
    the browser, not Outlook. Outlook for Windows renders with Word's engine and
    ignores most of what a div can do, which is why every box here is a table
    cell.

    The logo is a coloured cell with a "Z" in it rather than an image. The
    brand marks are SVG, which Gmail and Outlook do not display, and an image
    that is blocked by default shows the recipient a broken square at the top
    of a security email - the worst place for something to look wrong.
    ─────────────────────────────────────────────────────────────────────────────

    Sections: `preheader` (the grey preview line in the inbox list), `heading`,
    `content`.
--}}
@php
    $brand = config('zephryx.brand.name');
    $product = trim($brand.' '.config('zephryx.brand.suffix'));
    $support = config('zephryx.support.email');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $product }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #F2F5F3; -webkit-text-size-adjust: 100%;">

    {{-- The inbox preview line. Hidden in the body itself. --}}
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all; font-size: 1px; line-height: 1px; color: #F2F5F3;">
        @yield('preheader')
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #F2F5F3;">
        <tr>
            <td align="center" style="padding: 32px 16px;">

                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 600px;">

                    {{-- Header --}}
                    <tr>
                        <td style="padding: 0 4px 20px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="36" height="36" align="center" valign="middle"
                                        style="width: 36px; height: 36px; background-color: #0E7A36; border-radius: 9px;
                                               font-family: Arial, Helvetica, sans-serif; font-size: 20px; font-weight: 700; color: #FFFFFF;">
                                        Z
                                    </td>
                                    <td style="padding-left: 12px; font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                                               font-size: 17px; font-weight: 700; color: #0F2A1A;">
                                        {{ $brand }}
                                        <span style="font-weight: 400; color: #5C6B65;">{{ config('zephryx.brand.suffix') }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td style="background-color: #FFFFFF; border: 1px solid #E1E8E4; border-radius: 14px; overflow: hidden;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="height: 4px; line-height: 4px; font-size: 4px; background-color: #0E7A36; border-radius: 14px 14px 0 0;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td style="padding: 36px 40px 32px; font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                                               font-size: 15px; line-height: 1.65; color: #0F2A1A;">
                                        <h1 style="margin: 0 0 20px; font-size: 22px; line-height: 1.3; font-weight: 700; color: #0F2A1A;">
                                            @yield('heading')
                                        </h1>

                                        @yield('content')
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="padding: 24px 24px 0; font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                                                  font-size: 12px; line-height: 1.6; color: #7A8A83;">
                            This is an automated message from {{ $product }}. Please do not reply to it.
                            @if ($support)
                                <br>Questions? Write to
                                <a href="mailto:{{ $support }}" style="color: #0E7A36; text-decoration: none;">{{ $support }}</a>.
                            @endif
                            <br><br>&copy; {{ now()->year }} {{ $brand }}. All rights reserved.
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>
</body>
</html>
