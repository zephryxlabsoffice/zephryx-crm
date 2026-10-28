Hello {!! $name !!},

{!! $address !!} was added to your {!! config('zephryx.brand.name') !!} account and can be used to sign in. Open this link to confirm it is yours:

{!! $url !!}

The link works for {!! $hours !!} hours. If it has expired, ask for a new one from My Profile.

If you did not expect this, tell HR before using the link.

@include('mail.text.footer')
