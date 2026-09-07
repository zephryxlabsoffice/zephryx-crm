@php use App\Support\AttendancePresenter as P; @endphp

{{--
    What kind of day this was, said above the roll.

    A Sunday is not a company-wide absence, and a page showing eleven red rows
    on one would be read as broken before it was read as a weekend.

    Expects: $holiday (string|null), $workingDay (bool), $headcount (int),
             $holidayWorked (int), $holidayAnnouncement (string|null).

    Its own partial so the doubt branch below can be rendered in a test. It
    cannot occur in the demo data by construction — nobody has a record on a
    holiday, because the generator does not write records on non-working days —
    and a branch that only appears when something has gone wrong is exactly the
    one that gets shipped broken.
--}}

@if ($holiday !== null && P::holidayLooksWrong($holidayWorked, $headcount))
    {{--
        The holiday itself is in doubt.

        Holidays come from the announcements, so a mis-typed date on a notice
        closes the office on a day it was open — and the damage is quiet,
        because it hides absences rather than showing anything wrong. Nothing is
        maintained to catch this: a real closure has a handful of records, a
        wrong date has most of the company, and that is the signal. See
        AttendancePresenter::holidayLooksWrong.
    --}}
    @include('partials.notice', [
        'tone' => 'warning',
        'title' => 'This day is marked a holiday, but '.$holidayWorked.' of '.$headcount.' people checked in',
        'message' => 'That is enough of the company that the closure date may be wrong rather than the attendance. '
            .'Nobody here is being counted absent, which is worth knowing before this month is read as a good one. '
            .'The dates come from the announcement — “'.$holiday.'” — and correcting them there corrects this.',
    ])

    @if ($holidayAnnouncement !== null)
        <p class="att-notice-link">
            <a class="card-link" href="{{ route('announcements.show', ['announcement' => $holidayAnnouncement]) }}">
                Open the announcement that closed this day
            </a>
        </p>
    @endif
@elseif ($holiday !== null)
    @include('partials.notice', [
        'tone' => 'info',
        'title' => $holiday,
        'message' => 'A company holiday, from the announcement of the same name. Nobody was expected in, so nobody is counted absent'
            .($holidayWorked > 0 ? ' — and '.$holidayWorked.' '.($holidayWorked === 1 ? 'person' : 'people').' checked in anyway.' : '.'),
    ])
@elseif (! $workingDay)
    @include('partials.notice', [
        'tone' => 'info',
        'title' => 'A weekly off',
        'message' => 'Not a working day under the current policy. Anyone who did check in is still shown, because work that happened should not disappear.',
    ])
@endif
