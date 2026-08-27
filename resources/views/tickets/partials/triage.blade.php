@php use App\Support\TicketPresenter as P; @endphp

{{--
    Triage: the step that turns a raw ticket into a routed one.

    Shown only when the ticket needs it — unassigned, or escalated back for
    someone else to route. The handover put this panel on a separate
    "reviewer" page, which meant a reviewer had to know which of four URLs to
    open; here it appears on the ticket when the ticket needs it.
--}}
<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M22 3H2l8 9.46V19l4 2v-8.54z"/>
        </svg>
        {{ $ticket['status'] === 'escalated' ? 'Review and reassign' : 'Triage this ticket' }}
    </div>

    <div class="prose">
        <p>
            @if ($ticket['status'] === 'escalated')
                {{ $ticket['escalator_record']['name'] ?? 'Someone' }} escalated this because they could
                not resolve it. Route it to whoever can.
            @else
                Nobody has picked this up. Set what it is and who should handle it.
            @endif
        </p>
    </div>

    <form class="triage-grid" method="POST" action="{{ route('tickets.triage', ['ticket' => $ticket['id']]) }}">
        @csrf

        <div class="triage-field">
            <label class="triage-lbl" for="triage-priority">Priority</label>
            <select id="triage-priority" name="priority" disabled>
                <option value="">Select priority</option>
                @foreach (P::priorityOptions() as $option)
                    <option value="{{ $option }}" @selected($ticket['priority'] === $option)>{{ P::priority($option)['label'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="triage-field">
            <label class="triage-lbl" for="triage-category">Category</label>
            <select id="triage-category" name="category" disabled>
                <option value="">Select category</option>
                @foreach ($categories as $option)
                    <option value="{{ $option }}" @selected($ticket['category'] === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>

        <div class="triage-field">
            <label class="triage-lbl" for="triage-department">Department</label>
            <select id="triage-department" name="department" disabled>
                <option value="">Select department</option>
                @foreach ($departments as $option)
                    <option value="{{ $option }}" @selected($ticket['department'] === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>

        <div class="triage-field">
            <label class="triage-lbl" for="triage-assignee">Assign to</label>
            <select id="triage-assignee" name="assignee" disabled>
                <option value="">Select someone</option>
                @foreach ($agents as $agent)
                    <option value="{{ $agent['id'] }}" @selected($ticket['assignee'] === $agent['id'])>{{ $agent['name'] }}</option>
                @endforeach
            </select>
        </div>

        <div class="triage-actions">
            {{-- TODO (backend phase): assigning is a write, and §2.6 applies —
                 nobody may route a ticket to someone who outranks them in the
                 `support` domain. --}}
            <button class="btn btn-primary" type="submit" disabled title="Assigning is not built yet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Assign ticket
            </button>
        </div>
    </form>
</div>
