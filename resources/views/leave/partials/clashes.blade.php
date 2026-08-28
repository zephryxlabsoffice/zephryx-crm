@php
    use App\Support\Avatar;
    use App\Support\LeavePolicy;
    use App\Support\LeavePresenter as P;
@endphp

{{--
    Who else is off across these dates.

    The handover had no equivalent, and this is the information the decision
    actually turns on. Approving leave without it is how a team ends up with
    nobody in on a Friday, and the person who approved it finds out on the
    Friday.

    Pending requests are shown as well as approved ones: two people asking for
    the same week is precisely the clash worth catching before either is granted.
--}}
<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        Also away on these dates
        @if ($clashes->isNotEmpty())
            <span class="tab-count">{{ $clashes->count() }}</span>
        @endif
    </div>

    @if ($clashes->isEmpty())
        <div class="card-body">
            <p class="rail-empty">Nobody else is booked off across these dates.</p>
        </div>
    @else
        <ul class="lv-clashes">
            @foreach ($clashes as $clash)
                @php
                    $clashPill = P::status($clash['status']);
                    $clashType = LeavePolicy::type($clash['type']);
                @endphp
                <li class="lv-clash">
                    <span class="avatar {{ Avatar::tint($clash['employee_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($clash['employee_record']['name']) }}</span>

                    <span class="lv-clash-body">
                        <strong>{{ $clash['employee_record']['name'] }}</strong>
                        <span>{{ $clash['employee_record']['department'] }} · {{ P::range($clash) }}</span>
                    </span>

                    <span class="lv-clash-meta">
                        {{-- A pending clash is not the same as a granted one:
                             one is a fact, the other is a decision somebody
                             still gets to make. --}}
                        <span class="pill {{ $clashPill['tone'] }}">{{ $clashPill['label'] }}</span>
                        <a class="card-link" href="{{ route('leave.show', ['leaveRequest' => $clash['id']]) }}">Open</a>
                    </span>
                </li>
            @endforeach
        </ul>

        @php $sameDept = $clashes->filter(fn (array $c) => $c['employee_record']['department'] === $request['employee_record']['department']); @endphp
        @if ($sameDept->isNotEmpty())
            {{-- Same-department overlap is the one that actually hurts: two
                 designers off together is a problem in a way that a designer
                 and an accountant is not. --}}
            <p class="lv-clash-warn">
                {{ $sameDept->count() }} of {{ $sameDept->count() === 1 ? 'them is' : 'them are' }}
                also in {{ $request['employee_record']['department'] }}.
            </p>
        @endif
    @endif
</div>
