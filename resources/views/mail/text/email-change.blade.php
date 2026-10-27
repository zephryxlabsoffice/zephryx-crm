Hello {!! $name !!},

@if ($half === 'old')
Somebody asked to change the address you sign in to {!! config('zephryx.brand.name') !!} {!! config('zephryx.brand.suffix') !!} with.
@else
This address has been given as the new sign-in address for a {!! config('zephryx.brand.name') !!} {!! config('zephryx.brand.suffix') !!} account.
@endif

From: {!! $fromEmail !!}
To:   {!! $toEmail !!}

@if ($half === 'old')
If that was you, confirm it with the link below. The new address has to confirm as well, and nothing changes until both have.
@else
Confirm with the link below. The old address has to confirm as well, and nothing changes until both have.
@endif

{!! $url !!}

The link expires in {!! $hours !!} hours and works once.

@if ($half === 'old')
Didn't ask for this? Do nothing. The change cannot go through without this link, and it expires on its own. Then change your password and tell the owner.@if ($ip) The request came from {!! $ip !!}.@endif

@else
If you were not expecting this, ignore it. Nothing about this address changes unless the link is opened.
@endif

@include('mail.text.footer')
