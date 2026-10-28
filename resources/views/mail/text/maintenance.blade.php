@php $brand = config('zephryx.brand.name'); @endphp
Hello,

@if ($kind === 'started')
We are doing maintenance work on {!! $brand !!} right now, so the site cannot be used until it is finished. We will email you again as soon as it is back.
@elseif ($kind === 'scheduled')
{!! $brand !!} will be closed for maintenance during the time below. Please save your work and sign out before it starts; the site cannot be used until it ends.

From: {!! $from !!}
To:   {!! $to !!}
(Office time, IST.)
@elseif ($kind === 'ended')
The maintenance work is finished and {!! $brand !!} is open again. Thank you for your patience.

{!! $url !!}
@else
The planned maintenance has been called off. {!! $brand !!} stays open as usual.
@endif
@if ($note)

{!! $note !!}
@endif

@include('mail.text.footer')
