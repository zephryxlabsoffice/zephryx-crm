Hello {!! $name !!},

An email address on your {!! config('zephryx.brand.name') !!} account was changed from {!! $oldAddress !!} to {!! $newAddress !!}.

If you asked for this, there is nothing to do. If you did not, open this link to lock your account: it will be signed out everywhere, and the administrators will be told straight away.

{!! $notMeUrl !!}

@include('mail.text.footer')
