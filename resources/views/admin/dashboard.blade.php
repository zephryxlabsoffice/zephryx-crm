@extends('layouts.app')

@php
    use App\Support\Avatar;
    use App\Support\Admin\AuditDirectory;
@endphp

@section('title', 'Overview')

@section('content')
    <div class="page-hd">
        <h1>Overview</h1>
        <p>Whether this system is set up the way you meant it to be.</p>
    </div>

    {{--
        Not a company dashboard. §2.1 gives this account no operational
        authority and no personal records, and the owner holds a staff account
        for the headcount-and-revenue view. What is here is configuration state
        and recent actions — loose ends and a log.

        If this page is boring, the configuration is in order. That is the
        correct resting state for an administration screen.
    --}}

    @if ($attention !== [])
        <section class="kpi-row" aria-label="Needs attention">
            @foreach ($attention as $item)
                <a class="kpi kpi-link" href="{{ route($item['route']) }}">
                    <div class="kpi-ic {{ $item['tone'] }}" aria-hidden="true">
                        @include('partials.nav-icon', ['icon' => 'settings'])
                    </div>
                    <div class="kpi-body">
                        <div class="kpi-lbl">{{ $item['label'] }}</div>
                        <div class="kpi-val">{{ $item['count'] }}</div>
                        <span class="kpi-sub">{{ $item['note'] }}</span>
                    </div>
                </a>
            @endforeach
        </section>
    @endif

    <section class="dash-grid">
        <div class="dash-main">
            <section class="card">
                <div class="card-hd">
                    <span class="card-title">Who can do the sensitive things</span>
                    <a class="card-link" href="{{ route('admin.access.index') }}">Access control</a>
                </div>

                <div class="card-body">
                    {{--
                        The union across everybody's roles, which is the one
                        question about permissions that cannot be answered by
                        reading a list of roles (§2.4).
                    --}}
                    <p class="ad-group-note">
                        Counted across each person's roles combined. Somebody who is both an
                        Employee and HR holds the union of the two.
                    </p>

                    <div class="ad-sensitive-grid">
                        @foreach ($sensitive as $item)
                            <div class="ad-sensitive-row">
                                <div class="ad-sensitive-key">
                                    <strong>{{ $item['key'] }}</strong>
                                    <span>{{ $item['holders']->count() === 1 ? '1 person' : $item['holders']->count().' people' }}</span>
                                </div>

                                <div class="ad-people">
                                    @forelse ($item['holders'] as $person)
                                        <span class="avatar {{ Avatar::tint($person['name']) }}" title="{{ $person['name'] }}">
                                            {{ Avatar::initials($person['name']) }}
                                        </span>
                                    @empty
                                        <span class="dash-quiet-meta">Nobody</span>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="card table-card">
                <div class="card-hd">
                    <span class="card-title">Recent activity</span>
                    <a class="card-link" href="{{ route('admin.audit.index') }}">Audit log</a>
                </div>

                <div class="card-body-table">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">What happened</th>
                                <th scope="col">Kind</th>
                                <th scope="col">When</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recent as $entry)
                                @php $meta = AuditDirectory::kind($entry['kind']); @endphp
                                <tr>
                                    <td>
                                        <a class="row-link" href="{{ route('admin.audit.show', $entry['id']) }}">
                                            <strong>{{ $entry['action'] }}</strong>
                                            @if ($entry['after'])
                                                <span class="dash-sub">{{ $entry['after'] }}</span>
                                            @endif
                                        </a>
                                    </td>
                                    <td class="cell-tight"><span class="pill {{ $meta['tone'] }}">{{ $meta['label'] }}</span></td>
                                    <td class="cell-tight">{{ $entry['at']->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        <div class="table-empty">
                                            <strong>Nothing recorded yet</strong>
                                            <span>Entries appear here as things happen.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Accounts</strong>
                    <a class="dash-link" href="{{ route('admin.accounts.index') }}">All</a>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Can sign in</span>
                    <span class="stat-value">{{ $accounts['active'] }} <span class="stat-value-quiet">of {{ $accounts['total'] }}</span></span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Suspended</span>
                    <span class="stat-value">{{ $accounts['suspended'] }}</span>
                </div>
            </section>

            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Worth a look</strong>
                </div>

                @if ($notable->isEmpty())
                    <p class="rail-empty">Nothing unusual.</p>
                @else
                    {{-- Refused sign-ins and permission grants. Everything else
                         is normal traffic and belongs in the log, not here. --}}
                    <div class="rail-list">
                        @foreach ($notable as $entry)
                            <a class="rail-row" href="{{ route('admin.audit.show', $entry['id']) }}">
                                <div class="rail-body">
                                    <strong>{{ $entry['action'] }}</strong>
                                    <span>{{ $entry['actor'] }}</span>
                                </div>
                                <span class="rail-time">{{ $entry['at']->diffForHumans(short: true) }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        </aside>
    </section>
@endsection
