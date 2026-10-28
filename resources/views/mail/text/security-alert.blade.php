Hello,

Somebody using {!! $address !!} pressed "This wasn't me" on {!! $what !!} email for {!! $name !!} ({!! $staffId !!}). The account is on hold for security: nobody can sign in, and it was signed out everywhere.

Check with {!! $name !!} in person. When it is safe, open the account and press Restore: they will be emailed a link to choose a new password.

{!! $url !!}

@include('mail.text.footer')
