@php use App\Support\LeavePolicy; @endphp

{{--
    The balance, per leave type.

    Bars rather than a donut. The handover used a donut whose segments were sized
    with inline `style="transform: rotate(...)"` and whose legend coloured itself
    with `style="--dot:#3B82F6"` — both blocked outright by our CSP, so it would
    have rendered as a grey ring beside a colourless list.

    Bars are also the better chart here: a donut shows how a whole divides into
    parts, but these are four independent allowances, each with its own maximum.
    A donut of them implies they add up to something, which they do not.

    Every figure is entitlement minus approved days. Nothing is estimated: this
    module counts what was recorded rather than working out what should have
    been.
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>{{ $heading ?? 'Leave balance' }}</strong>
        <span class="lv-bal-total">{{ $balance['remaining'] }} of {{ $balance['entitlement'] }} left</span>
    </div>

    <ul class="lv-bal">
        @foreach ($balance['types'] as $row)
            <li class="lv-bal-row">
                <span class="lv-bal-hd">
                    <span class="lv-dot {{ $row['tone'] }}" aria-hidden="true"></span>
                    <strong>{{ $row['label'] }}</strong>
                    <span class="lv-bal-fig">{{ $row['remaining'] }} / {{ $row['entitlement'] }}</span>
                </span>

                {{-- Native <progress>: it takes an attribute rather than a style,
                     which our CSP allows, and it announces itself to a screen
                     reader without ARIA of our own. --}}
                <label class="sr-only" for="bal-{{ $row['key'] }}">{{ $row['label'] }} remaining</label>
                <progress id="bal-{{ $row['key'] }}" class="progress" max="{{ $row['entitlement'] }}" value="{{ $row['remaining'] }}"></progress>

                <span class="lv-bal-note">
                    {{ $row['taken'] }} taken
                    @if ($row['pending'] > 0)
                        {{-- Pending days are stated, never netted off: they have
                             not been granted, and showing a balance that already
                             assumes approval is how somebody plans around days
                             they may not get. --}}
                        · <strong>{{ $row['pending'] }} awaiting a decision</strong>
                    @endif
                </span>
            </li>
        @endforeach
    </ul>

    @if ($balance['unpaid'] > 0)
        <p class="lv-bal-unpaid">
            Plus {{ $balance['unpaid'] }} {{ \Illuminate\Support\Str::plural('day', $balance['unpaid']) }}
            of unpaid leave taken. Unpaid leave has no allowance, so it is
            counted but not deducted from anything.
        </p>
    @endif

    <p class="lv-policy-note">
        This year only.
        @if (LeavePolicy::carryForward() === null)
            Whether unused days carry into next year has not been settled.
        @endif
    </p>
</section>
