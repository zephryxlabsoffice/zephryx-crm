@php
    use App\Support\Avatar;
    use App\Support\Milestones;
@endphp

{{--
    Birthdays and work anniversaries.

    Computed from employee records on every request, never stored: a stored
    milestone is wrong the following year and outlives somebody opting out of
    their own being announced. Milestones::upcoming honours the opt-out and
    skips anybody who has left.

    The year is never shown. A birthday is a day and a month; the year is the
    person's age, which is theirs to mention.
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>Coming up</strong>
    </div>

    @if ($w['items'] === [])
        <p class="rail-empty">Nothing in the next fortnight.</p>
    @else
        <div class="rail-list">
            @foreach ($w['items'] as $milestone)
                <div class="rail-row">
                    <div class="avatar {{ Avatar::tint($milestone['employee']['name']) }}" aria-hidden="true">
                        {{ Avatar::initials($milestone['employee']['name']) }}
                    </div>
                    <div class="rail-body">
                        <strong>{{ $milestone['employee']['name'] }}</strong>
                        <span>
                            {{ $milestone['kind'] === Milestones::BIRTHDAY ? 'Birthday' : 'Work anniversary' }}
                            · {{ $milestone['day_month'] }}
                        </span>
                    </div>
                    <span class="rail-time">{{ $milestone['label'] }}</span>
                </div>
            @endforeach
        </div>
    @endif
</section>
