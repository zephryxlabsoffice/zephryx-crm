Hello {!! $name !!},

{!! $address !!} was added to your {!! config('zephryx.brand.name') !!} account and can be used to sign in. Open this link to confirm it is yours:

{!! $url !!}

The link works for {!! $hours !!} hours. If it has expired, ask for a new one from My Profile.

Did not ask for this? Do not verify. Open this link instead to lock the account; the administrators are told:

{!! $notMeUrl !!}

@include('mail.text.footer')
