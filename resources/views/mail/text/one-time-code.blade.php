Hello {!! $name !!},

Use this code to finish signing in to {!! config('zephryx.brand.name') !!} {!! config('zephryx.brand.suffix') !!}:

    {!! $code !!}

It expires in {!! $minutes !!} minutes and can be used once.

Didn't try to sign in? Somebody else has your password. Change it as soon as you can and tell your administrator.@if ($ip) The attempt came from {!! $ip !!}.@endif


Nobody from {!! config('zephryx.brand.name') !!} will ever phone or email to ask you for this code.

@include('mail.text.not-me')

@include('mail.text.footer')
