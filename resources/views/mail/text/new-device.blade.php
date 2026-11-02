Hello {!! $name !!},

Your {!! config('zephryx.brand.name') !!} {!! config('zephryx.brand.suffix') !!} account was just signed in on a device it has not used before.

Device: {!! $deviceName !!}
Location: {!! $location !!}
@if ($ip)
IP address: {!! $ip !!}
@endif
Time: {!! $signedInAt !!}

If this was you, there is nothing to do. The location is an estimate and can be off by a city or more. You can see everywhere you are signed in on My Devices:

{!! $devicesUrl !!}

If this was not you, open this link to lock your account: it will be signed out everywhere, and the administrators will be told straight away.

{!! $notMeUrl !!}

@include('mail.text.footer')
