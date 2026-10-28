{{--
    The red "This wasn't me" button - mail.partials.button in the danger colour.

    @include('mail.partials.alarm-button', ['url' => …])
--}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 8px 0 20px;">
    <tr>
        <td align="center" bgcolor="#C03B3B" style="background-color: #C03B3B; border-radius: 10px;">
            <a href="{{ $url }}" target="_blank"
               style="display: inline-block; padding: 12px 26px; font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                      font-size: 14px; font-weight: 700; color: #FFFFFF; text-decoration: none; border-radius: 10px;">This wasn't me</a>
        </td>
    </tr>
</table>
