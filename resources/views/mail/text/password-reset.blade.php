Hello {!! $name !!},

We received a request to reset the password on your {!! config('zephryx.brand.name') !!} {!! config('zephryx.brand.suffix') !!} account. Open this link to choose a new one:

{!! $url !!}

The link expires in {!! $minutes !!} minutes and works once.

If you did not ask for this, you can ignore this email. Your password will not change unless somebody opens that link.@if ($ip) The request came from {!! $ip !!}.@endif


@include('mail.text.footer')
