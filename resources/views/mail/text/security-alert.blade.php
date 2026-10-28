Hello,

Somebody using {!! $address !!} pressed "This wasn't me" on an email about a change to {!! $name !!}'s ({!! $staffId !!}) email address. The account has been locked: it is suspended, and it was signed out everywhere.

Check with {!! $name !!} in person. If the change was genuine, correct the email if needed and set the account back to Active from the Admin Panel. If it was not, find out who made the change before unlocking it.

{!! $url !!}

@include('mail.text.footer')
