@php
    use App\Support\Avatar;
    use App\Support\Milestones;
@endphp

{{--
    Birthdays and work anniversaries coming up.

    Computed from employee records on every request, never stored: a stored
    birthday post would be wrong the following year and would survive somebody
    opting out of their own.

    A birthday shows a day and a month and never a year — a colleague needs to
    know when to say happy birthday, not how old somebody is. An anniversary
    does show its year count, because "three years today" is the whole point.
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>Coming up</strong>
    </div>

    @if ($upcoming === [])
        <p class="rail-empty">Nothing in the next fortnight.</p>
    @else
        <ul class="rail-list">
            @foreach ($upcoming as $milestone)
                <li class="rail-row">
                    <span class="avatar {{ Avatar::tint($milestone['employee']['name']) }}" aria-hidden="true">{{ Avatar::initials($milestone['employee']['name']) }}</span>

                    <div class="rail-body">
                        <strong>{{ $milestone['employee']['name'] }}</strong>
                        <span>
                            @if ($milestone['kind'] === Milestones::BIRTHDAY)
                                Birthday · {{ $milestone['day_month'] }}
                            @else
                                {{ Milestones::years($milestone['years']) }} · {{ $milestone['day_month'] }}
                            @endif
                        </span>
                    </div>

                    <div class="rail-meta">
                        <strong class="{{ $milestone['in_days'] === 0 ? 'is-today' : '' }}">{{ $milestone['label'] }}</strong>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
