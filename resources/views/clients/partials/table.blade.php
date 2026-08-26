@php use App\Support\ClientPresenter as P; @endphp

<div class="card table-card">
    <div class="card-hd">
        <span class="card-title">All Clients</span>

        {{-- A real GET form: search and filter end up in the URL, so a filtered
             list can be bookmarked, shared and reloaded. It submits without
             JavaScript. --}}
        <form class="table-tools" method="GET" action="{{ route('clients.index') }}">
            <div class="search-input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <label class="sr-only" for="client-search">Search clients</label>
                <input id="client-search" type="search" name="q" value="{{ $search }}" placeholder="Search client…">
            </div>

            <label class="sr-only" for="client-status">Filter by status</label>
            {{-- `data-auto-submit` rather than an onchange attribute: inline
                 handlers are blocked by our Content-Security-Policy (§6). --}}
            <select class="chip-btn" id="client-status" name="status" data-auto-submit>
                <option value="">All statuses</option>
                @foreach (['active' => 'Active', 'pending' => 'Pending', 'review' => 'In Review', 'on_hold' => 'On Hold', 'completed' => 'Completed'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <button class="chip-btn" type="submit">Search</button>

            {{-- The handover's "View All" sat beside search with nothing to
                 view-all *to*. It only means something once a filter is on, so
                 it appears then, as a reset. --}}
            @if ($filtered)
                <a class="chip-btn chip-btn-accent" href="{{ route('clients.index') }}">Clear filters</a>
            @endif
        </form>
    </div>

    <div class="card-body-table">
        {{-- Roles are stated explicitly because the mobile rules below set
             `display: block` on these elements, which drops the implicit table
             semantics a screen reader relies on. --}}
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Client Name</th>
                    <th role="columnheader" scope="col">Industry</th>
                    <th role="columnheader" scope="col">Project</th>
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col">Last Activity</th>
                    <th role="columnheader" scope="col">Payment</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($clients as $client)
                    @php
                        $statusPill = P::status($client['status']);
                        $paymentPill = P::payment($client['payment']);
                    @endphp
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Client">
                            <a class="row-link cl-name" href="{{ route('clients.show', ['client' => \Illuminate\Support\Str::slug($client['name'])]) }}">
                                <span class="avatar {{ P::tint($client['name']) }}" aria-hidden="true">{{ P::initial($client['name']) }}</span>
                                <strong>{{ $client['name'] }}</strong>
                            </a>
                        </td>
                        <td role="cell" data-label="Industry">{{ $client['industry'] }}</td>
                        <td role="cell" data-label="Project">{{ $client['project'] }}</td>
                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill {{ $statusPill['tone'] }}">{{ $statusPill['label'] }}</span>
                        </td>
                        <td role="cell" class="cell-tight" data-label="Last activity">{{ $client['activity'] }}</td>
                        <td role="cell" class="cell-tight" data-label="Payment">
                            <span class="pill {{ $paymentPill['tone'] }}">{{ $paymentPill['label'] }}</span>
                        </td>
                        <td role="cell" class="cell-actions">
                            <button class="row-menu" type="button" disabled title="Row actions are not built yet">
                                <span class="sr-only">Actions for {{ $client['name'] }}</span>
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr role="row">
                        <td role="cell" colspan="7">
                            <div class="table-empty">
                                @if ($filtered)
                                    <strong>No clients match that search.</strong>
                                    Try a different term, or <a class="card-link" href="{{ route('clients.index') }}">clear the filters</a>.
                                @else
                                    <strong>No clients yet.</strong>
                                    Clients added by an administrator will appear here.
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($clients->hasPages() || $clients->total() > 0)
        @include('partials.pagination', ['paginator' => $clients, 'unit' => 'clients'])
    @endif
</div>
