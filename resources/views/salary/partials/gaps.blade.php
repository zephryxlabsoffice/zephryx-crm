@php use App\Support\Avatar; @endphp

{{--
    Who is NOT in this month's payroll.

    The handover had no equivalent, and its absence is the module's most
    dangerous gap: a list of everyone who IS being paid tells you nothing about
    the person who is missing from it, and the person who is missing from it is
    somebody who does not get paid. A payroll screen has to be as good at
    showing absence as presence.

    Two different problems, deliberately separated — "nothing has been generated
    for them yet" is a step to take, "there is no structure to generate from" is
    a decision somebody has to make first.
--}}
<section class="card sl-gaps">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
            <path d="M12 9v4M12 17h.01"/>
        </svg>
        Nobody should be missing from payroll
    </div>

    <div class="card-body sl-gap-grid">
        @if ($missing->isNotEmpty())
            <div class="sl-gap">
                <strong class="sl-gap-hd">No run this month</strong>
                <p class="sl-gap-note">
                    {{ $missing->count() }} {{ \Illuminate\Support\Str::plural('person', $missing->count()) }}
                    with a salary structure {{ $missing->count() === 1 ? 'has' : 'have' }} nothing generated for
                    {{ \App\Support\SalaryPresenter::period($period) }}.
                </p>
                <ul class="sl-gap-list">
                    @foreach ($missing as $person)
                        <li>
                            <span class="avatar {{ Avatar::tint($person['name']) }}" aria-hidden="true">{{ Avatar::initials($person['name']) }}</span>
                            <span class="sl-gap-person">
                                <strong>{{ $person['name'] }}</strong>
                                <span>{{ $person['user_id'] }} · {{ $person['department'] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($withoutStructure->isNotEmpty())
            <div class="sl-gap sl-gap-severe">
                <strong class="sl-gap-hd">No salary structure at all</strong>
                <p class="sl-gap-note">
                    {{ $withoutStructure->count() }} {{ \Illuminate\Support\Str::plural('person', $withoutStructure->count()) }}
                    cannot be paid until somebody records what {{ $withoutStructure->count() === 1 ? 'they earn' : 'they earn' }}.
                </p>
                <ul class="sl-gap-list">
                    @foreach ($withoutStructure as $person)
                        <li>
                            <span class="avatar {{ Avatar::tint($person['name']) }}" aria-hidden="true">{{ Avatar::initials($person['name']) }}</span>
                            <span class="sl-gap-person">
                                <strong>{{ $person['name'] }}</strong>
                                <span>{{ $person['user_id'] }} · joined {{ \App\Support\SalaryPresenter::date($person['joined']) }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</section>
