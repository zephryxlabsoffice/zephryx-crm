@php use App\Support\Avatar; @endphp

{{--
    Who raised it. A client is an organisation, an employee is a person — the
    handover drew both with a round avatar, which made a company look like a
    colleague. A client keeps the square chip used for organisations elsewhere.
--}}
@if ($ticket['client'])
    <span class="tkt-person">
        <span class="chip {{ Avatar::tint($ticket['client']) }}" aria-hidden="true">{{ Avatar::letter($ticket['client']) }}</span>
        <span class="tkt-person-text">
            <strong>{{ $ticket['client'] }}</strong>
            <span>Client</span>
        </span>
    </span>
@elseif ($ticket['raiser_record'])
    <span class="tkt-person">
        <span class="avatar {{ Avatar::tint($ticket['raiser_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($ticket['raiser_record']['name']) }}</span>
        <span class="tkt-person-text">
            <strong>{{ $ticket['raiser_record']['name'] }}</strong>
            <span>{{ $ticket['raiser_record']['designation'] }}</span>
        </span>
    </span>
@else
    <span class="tkt-unassigned">Unknown</span>
@endif
