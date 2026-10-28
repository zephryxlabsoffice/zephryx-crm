Hello {!! $name !!},

{!! $addedBy !!} at {!! config('zephryx.brand.name') !!} has set up a client portal account for {!! $company !!}. In the portal you can follow your projects, see and download your invoices, raise support tickets, request meetings and read announcements we share with you.

Your client ID: {!! $clientId !!}
You can sign in with this or with your email address.

Choose your password to get started:

{!! $url !!}

The link expires in {!! $minutes !!} minutes and works once. If it has expired, go to {!! $portal !!} and use "Forgot password" to get a fresh one.

If you were not expecting this, reply to let us know before using the link.

@include('mail.text.footer')
