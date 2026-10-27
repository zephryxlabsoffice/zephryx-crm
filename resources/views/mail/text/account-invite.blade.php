Hello {!! $name !!},

{!! $addedBy !!} has set up your {!! config('zephryx.brand.name') !!} {!! config('zephryx.brand.suffix') !!} account. Open this link to choose a password and sign in for the first time:

{!! $url !!}

Your staff ID: {!! $staffId !!}
You sign in with your email address; the staff ID is what appears on your records.

The link expires in {!! $minutes !!} minutes and works once. If it has expired, use "Forgot password" on the sign-in page to get a fresh one. Your account is already set up.

If you were not expecting this, speak to {!! $addedBy !!} before using the link.

@include('mail.text.footer')
