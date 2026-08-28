@php
    use App\Support\Avatar;
    $shown = array_slice($attendees, 0, 4);
    $extra = count($attendees) - count($shown);
@endphp

{{--
    Who is on the invite, at a glance.

    The handover showed no attendees anywhere — a meeting without them is not a
    meeting, and its rail hardcoded "With: Santanu Kumar". A client is drawn as
    a square chip and a colleague as a round avatar, so the column tells you
    whether this is a client call without being read.
--}}
<span class="mt-faces">
    @foreach ($shown as $attendee)
        <span class="{{ $attendee['kind'] === 'client' ? 'mt-face mt-face-client' : 'avatar mt-face' }} {{ Avatar::tint($attendee['name']) }}"
              title="{{ $attendee['name'] }}">{{ Avatar::initials($attendee['name']) }}</span>
    @endforeach

    @if ($extra > 0)
        <span class="mt-face mt-face-more">+{{ $extra }}</span>
    @endif

    {{-- The names in text as well, because a row of coloured circles is not
         readable to a screen reader and the `title` attribute is not either. --}}
    <span class="sr-only">{{ collect($attendees)->pluck('name')->join(', ') }}</span>
</span>
