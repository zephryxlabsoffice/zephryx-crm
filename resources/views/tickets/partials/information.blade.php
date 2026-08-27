@php use App\Support\TicketPresenter as P; @endphp

<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/><path d="M12 16v-5M12 8h.01"/>
        </svg>
        Ticket Information
    </div>

    <div class="prose">
        <p>{{ $ticket['description'] }}</p>
    </div>

    <div class="info-columns">
        <div>
            <div class="info-row">
                <span>Status</span>
                <span><span class="pill {{ P::status($ticket['status'])['tone'] }}">{{ P::status($ticket['status'])['label'] }}</span></span>
            </div>
            <div class="info-row">
                <span>Priority</span>
                <span>
                    @if ($ticket['priority'])
                        <span class="priority {{ P::priority($ticket['priority'])['tone'] }}">{{ P::priority($ticket['priority'])['label'] }}</span>
                    @else
                        {{-- A blank cell hides the fact that triage still has to
                             happen; "Not set" says so. --}}
                        <span class="is-empty">Not set</span>
                    @endif
                </span>
            </div>
            <div class="info-row">
                <span>Category</span>
                <span>@if ($ticket['category']){{ $ticket['category'] }}@else<span class="is-empty">Not set</span>@endif</span>
            </div>
        </div>

        <div>
            <div class="info-row">
                <span>Department</span>
                <span>@if ($ticket['department']){{ $ticket['department'] }}@else<span class="is-empty">Not set</span>@endif</span>
            </div>
            <div class="info-row">
                <span>Project</span>
                <span>
                    @if ($ticket['project_record'])
                        <a href="{{ route('projects.show', ['project' => $ticket['project_record']['id']]) }}">{{ $ticket['project_record']['name'] }}</a>
                    @else
                        <span class="is-empty">Not linked</span>
                    @endif
                </span>
            </div>
            <div class="info-row">
                <span>Raised by</span>
                <span>{{ $ticket['client'] ?? ($ticket['raiser_record']['name'] ?? 'Unknown') }}</span>
            </div>
        </div>
    </div>
</div>
