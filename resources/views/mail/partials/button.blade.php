{{--
    A call-to-action button that survives Outlook: a table cell carrying the
    colour, with the link inside it. A styled <a> alone loses its padding there.

    @include('mail.partials.button', ['url' => …, 'label' => …])
--}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 8px 0 28px;">
    <tr>
        <td align="center" bgcolor="#0E7A36" style="background-color: #0E7A36; border-radius: 10px;">
            <a href="{{ $url }}" target="_blank"
               style="display: inline-block; padding: 14px 30px; font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                      font-size: 15px; font-weight: 700; color: #FFFFFF; text-decoration: none; border-radius: 10px;">{{ $label }}</a>
        </td>
    </tr>
</table>
