@if ($known)
Ticket created. Reference number is {!! $reference !!}.

You can check it in {!! $site !!} under Tickets. Reply to this email to add to it.

Open the ticket:

{!! $url !!}
@else
This is an automated reply. We will contact you shortly.

Kindly contact us using your registered email for quick resolution.

If in a hurry, call us.
@endif

@include('mail.text.footer')
