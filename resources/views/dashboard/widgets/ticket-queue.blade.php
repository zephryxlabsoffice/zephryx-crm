@php use App\Support\TicketPresenter as TP; @endphp

{{--
    The triage queue: escalated first, then unpicked.

    Escalations lead because an escalation is somebody saying the normal route
    did not work for them, and it ages worse than a ticket nobody has claimed
    yet.
--}}
<section class="card table-card">
    <div class="card-hd">
        <span class="card-title">Tickets needing someone</span>
        <a class="card-link" href="{{ route('tickets.index') }}">View all</a>
    </div>

    @if ($w['items']->isEmpty())
        <div class="card-body">
            <p class="rail-empty">Nothing escalated or unclaimed.</p>
        </div>
    @else
        <div class="card-body-table">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Ticket</th>
                        <th scope="col">Status</th>
                        <th scope="col">Priority</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($w['items'] as $ticket)
                        <tr>
                            <td>
                                <a class="row-link" href="{{ route('tickets.show', $ticket['id']) }}">
                                    <strong>{{ $ticket['subject'] }}</strong>
                                </a>
                            </td>
                            <td><span class="pill {{ TP::status($ticket['status'])['tone'] }}">{{ TP::status($ticket['status'])['label'] }}</span></td>
                            <td class="cell-tight">
                                <span class="priority priority-{{ $ticket['priority'] }}">{{ TP::priority($ticket['priority'])['label'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-body dash-more">
            <span>{{ $w['escalated'] }} escalated · {{ $w['unassigned'] }} unclaimed</span>
        </div>
    @endif
</section>
