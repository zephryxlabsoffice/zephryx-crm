@php
    use App\Support\Avatar;
    use App\Support\SalaryPresenter as P;
@endphp

{{--
    Who is not on this month's list at all.

    The handover had no equivalent, and its absence is the module's most
    dangerous gap: a list of everyone being paid tells you nothing about the
    person missing from it, and that person is the one who quietly does not get
    paid. A payroll screen has to be as good at showing absence as presence.

    Note this is a different problem from "no payslip yet", which is a status on
    the list below and a step somebody is about to take.
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
                <strong class="sl-gap-hd">Not on this month’s list</strong>
                <p class="sl-gap-note">
                    {{ $missing->count() }} {{ \Illuminate\Support\Str::plural('person', $missing->count()) }}
                    {{ $missing->count() === 1 ? 'has' : 'have' }} no salary record for
                    {{ P::period($period) }} at all.
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

        @if ($withoutBanking->isNotEmpty())
            <div class="sl-gap sl-gap-severe">
                <strong class="sl-gap-hd">No bank details on file</strong>
                <p class="sl-gap-note">
                    {{ $withoutBanking->count() }} {{ \Illuminate\Support\Str::plural('person', $withoutBanking->count()) }}
                    cannot be paid at all — the bank transfer file has nothing to
                    send for {{ $withoutBanking->count() === 1 ? 'them' : 'them' }}.
                </p>
                <ul class="sl-gap-list">
                    @foreach ($withoutBanking as $person)
                        <li>
                            <span class="avatar {{ Avatar::tint($person['name']) }}" aria-hidden="true">{{ Avatar::initials($person['name']) }}</span>
                            <span class="sl-gap-person">
                                <strong>{{ $person['name'] }}</strong>
                                <span>{{ $person['user_id'] }} · joined {{ P::date($person['joined']) }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</section>
