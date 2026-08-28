@php
    use App\Support\Avatar;
    use App\Support\LeavePolicy;
    use App\Support\LeavePresenter as P;
@endphp

{{--
    Who is off over the next fortnight.

    Not in the handover at all, and the most useful thing on the page: the
    question somebody opens this module to answer is usually "can I ask X about
    this on Thursday", not "how many requests were approved last year".
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>Away in the next two weeks</strong>
    </div>

    @if ($absences->isEmpty())
        <p class="rail-empty">Nobody is booked off in the next fortnight.</p>
    @else
        <ul class="rail-list">
            @foreach ($absences as $absence)
                @php $leaveType = LeavePolicy::type($absence['type']); @endphp
                <li class="rail-row">
                    <span class="avatar {{ Avatar::tint($absence['employee_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($absence['employee_record']['name']) }}</span>
                    <div class="rail-body">
                        <strong>{{ $absence['employee_record']['name'] }}</strong>
                        <span>{{ P::range($absence) }}</span>
                    </div>
                    <div class="rail-meta">
                        <strong>{{ P::duration($absence) }}</strong>
                        <span>{{ $leaveType['label'] }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
