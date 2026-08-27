@php
    use App\Support\Avatar;
    use App\Support\TicketPresenter as P;
@endphp

{{--
    The ticket list, shared by all five faces. `$action` is the route the
    filter form posts back to, so each list filters itself.

    `$columns` names which columns this face needs — the escalated queue wants
    "Escalated by", the personal lists do not need "Raised by" because it is
    always you.
--}}
@php
    $action = $action ?? route('tickets.index');
    $columns = $columns ?? ['type', 'raiser', 'assignee', 'status', 'updated'];
    $span = count($columns) + 3;
@endphp

<div class="card table-card">
    @isset($queues)
        {{-- Tabs are links with their own URL, so a queue can be bookmarked and
             the back button works. The handover used buttons. --}}
        <nav class="tabs" aria-label="Ticket queues">
            @foreach ($queues as $key => $label)
                <a class="tab @if ($tab === $key) active @endif"
                   href="{{ route('tickets.index', ['tab' => $key]) }}"
                   @if ($tab === $key) aria-current="page" @endif>
                    {{ $label }}
                    <span class="tab-count">{{ $tabCounts[$key] }}</span>
                </a>
            @endforeach
        </nav>
    @endisset

    <div class="card-hd">
        <span class="card-title">{{ $title ?? 'All Tickets' }}</span>

        <form class="table-tools" method="GET" action="{{ $action }}">
            @isset($tab)
                <input type="hidden" name="tab" value="{{ $tab }}">
            @endisset

            <div class="search-input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <label class="sr-only" for="ticket-search">Search tickets</label>
                <input id="ticket-search" type="search" name="q" value="{{ $search }}" placeholder="Search subject, ref or client…">
            </div>

            <label class="sr-only" for="ticket-type">Filter by type</label>
            <select class="chip-btn" id="ticket-type" name="type" data-auto-submit>
                <option value="">Internal and client</option>
                @foreach (P::typeOptions() as $option)
                    <option value="{{ $option }}" @selected($type === $option)>{{ P::typeLabel($option) }} only</option>
                @endforeach
            </select>

            <label class="sr-only" for="ticket-status">Filter by status</label>
            <select class="chip-btn" id="ticket-status" name="status" data-auto-submit>
                <option value="">All statuses</option>
                @foreach (P::statusOptions() as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ P::status($option)['label'] }}</option>
                @endforeach
            </select>

            <button class="chip-btn" type="submit">Search</button>

            @if ($filtered)
                <a class="chip-btn chip-btn-accent" href="{{ $action }}{{ isset($tab) ? '?tab='.$tab : '' }}">Clear filters</a>
            @endif
        </form>
    </div>

    <div class="card-body-table">
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Ticket</th>
                    @if (in_array('type', $columns, true))
                        <th role="columnheader" scope="col">Type</th>
                    @endif
                    @if (in_array('raiser', $columns, true))
                        <th role="columnheader" scope="col">Raised by</th>
                    @endif
                    @if (in_array('project', $columns, true))
                        <th role="columnheader" scope="col">Project</th>
                    @endif
                    @if (in_array('escalator', $columns, true))
                        <th role="columnheader" scope="col">Escalated by</th>
                    @endif
                    @if (in_array('assignee', $columns, true))
                        <th role="columnheader" scope="col">Assigned to</th>
                    @endif
                    <th role="columnheader" scope="col">Status</th>
                    @if (in_array('updated', $columns, true))
                        <th role="columnheader" scope="col">Updated</th>
                    @endif
                    <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($tickets as $item)
                    @php $statusPill = P::status($item['status']); @endphp
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Ticket">
                            <a class="row-link" href="{{ route('tickets.show', ['ticket' => $item['id']]) }}">
                                <span class="tkt-ref">{{ $item['id'] }}</span>
                                <strong class="tkt-subject">{{ $item['subject'] }}</strong>
                            </a>
                        </td>

                        @if (in_array('type', $columns, true))
                            <td role="cell" class="cell-tight" data-label="Type">
                                @include('tickets.partials.type-badge', ['type' => $item['type']])
                            </td>
                        @endif

                        @if (in_array('raiser', $columns, true))
                            <td role="cell" data-label="Raised by">
                                @include('tickets.partials.raiser', ['ticket' => $item])
                            </td>
                        @endif

                        @if (in_array('project', $columns, true))
                            <td role="cell" data-label="Project">
                                {{ $item['project_record']['name'] ?? '—' }}
                            </td>
                        @endif

                        @if (in_array('escalator', $columns, true))
                            <td role="cell" data-label="Escalated by">
                                @if ($item['escalator_record'])
                                    <span class="tkt-person">
                                        <span class="avatar {{ Avatar::tint($item['escalator_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($item['escalator_record']['name']) }}</span>
                                        <span class="tkt-person-text">
                                            <strong>{{ $item['escalator_record']['name'] }}</strong>
                                            <span>{{ $item['escalator_record']['designation'] }}</span>
                                        </span>
                                    </span>
                                @else
                                    <span class="tkt-unassigned">—</span>
                                @endif
                            </td>
                        @endif

                        @if (in_array('assignee', $columns, true))
                            <td role="cell" data-label="Assigned to">
                                @if ($item['assignee_record'])
                                    <span class="tkt-person">
                                        <span class="avatar {{ Avatar::tint($item['assignee_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($item['assignee_record']['name']) }}</span>
                                        <span class="tkt-person-text">
                                            <strong>{{ $item['assignee_record']['name'] }}</strong>
                                            <span>{{ $item['assignee_record']['designation'] }}</span>
                                        </span>
                                    </span>
                                @else
                                    <span class="tkt-unassigned">Nobody yet</span>
                                @endif
                            </td>
                        @endif

                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill {{ $statusPill['tone'] }}">{{ $statusPill['label'] }}</span>
                        </td>

                        @if (in_array('updated', $columns, true))
                            <td role="cell" class="cell-tight" data-label="Updated">
                                <span class="tkt-when">
                                    <strong>{{ P::date($item['updated_at']) }}</strong>
                                    <span>{{ P::time($item['updated_at']) }}</span>
                                </span>
                            </td>
                        @endif

                        <td role="cell" class="cell-actions">
                            <a class="row-menu" href="{{ route('tickets.show', ['ticket' => $item['id']]) }}">
                                <span class="sr-only">Open {{ $item['id'] }}</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr role="row">
                        <td role="cell" colspan="{{ $span }}">
                            <div class="table-empty">
                                @if ($filtered)
                                    <strong>No tickets match that search.</strong>
                                    Try a different term, or <a class="card-link" href="{{ $action }}">clear the filters</a>.
                                @else
                                    <strong>{{ $emptyTitle ?? 'No tickets yet.' }}</strong>
                                    {{ $emptyBody ?? 'Tickets raised by staff or clients will appear here.' }}
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($tickets->total() > 0)
        @include('partials.pagination', ['paginator' => $tickets, 'unit' => 'tickets'])
    @endif
</div>
