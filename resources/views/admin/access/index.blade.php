@extends('layouts.app')

@php use App\Support\Avatar; @endphp

@section('title', 'Access Control')

@section('content')
    <div class="page-hd">
        <h1>Access control</h1>
        <p>What each role may do, and where it ranks.</p>
    </div>

    {{--
        ─────────────────────────────────────────────────────────────────────────
        THE UNION, FIRST

        "Who can see payroll" is the question an owner opens this page with, and
        it cannot be answered by reading a list of roles: roles stack (§2.4), so
        the answer is a union that has to be worked out across every person's
        every role.

        So the page answers it before showing the roles at all.
        ─────────────────────────────────────────────────────────────────────────
    --}}
    <section class="card ad-sensitive">
        <div class="card-hd">
            <span class="card-title">Who can do the sensitive things</span>
        </div>

        <div class="card-body">
            <p class="ad-group-note">
                Counted across everybody's roles combined, not per role — somebody who
                is both an Employee and HR holds the union of the two (§2.4).
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
            <span class="card-title">Roles</span>
        </div>

        <div class="card-body-table">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Role</th>
                        <th scope="col">Permissions</th>
                        <th scope="col">People</th>
                        <th scope="col">Highest rank</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        @php
                            $top = collect($role['ranks'])->sortDesc();
                            $topDomain = $top->keys()->first();
                        @endphp
                        <tr>
                            <td>
                                <a class="row-link" href="{{ route('admin.access.show', $role['key']) }}">
                                    <strong>{{ $role['name'] }}</strong>
                                    <span class="dash-sub">{{ $role['description'] }}</span>
                                </a>
                            </td>
                            <td class="cell-tight">{{ count($role['permissions']) }}</td>
                            <td class="cell-tight">
                                {{ $role['holders']->count() }}
                                @if ($role['holders']->isEmpty())
                                    <span class="dash-quiet-meta">— nobody holds it</span>
                                @endif
                            </td>
                            <td class="cell-tight">
                                @if ($top->first() > 0)
                                    {{ $domains[$topDomain] ?? $topDomain }} · {{ $top->first() }}
                                @else
                                    {{-- Mentor: read-only, no operational standing anywhere. --}}
                                    <span class="dash-quiet-meta">No standing</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-hd">
            <span class="card-title">Rank, per domain</span>
        </div>

        <div class="card-body">
            {{--
                Kept well away from the permission lists, because the thing
                people assume about rank is exactly wrong: it grants nothing
                (§2.5). It answers "may I act on this person" and routes
                approvals, and that is all it does.

                The row worth reading is HR against System Administrator — HR
                outranks them in people and finance and is outranked in system,
                which is why rank is per-domain and not one ladder.
            --}}
            <p class="ad-group-note">
                Rank never grants a permission. It answers whether one person may act on
                another, and which way an approval routes — nothing else (§2.5).
            </p>

            <div class="card-body-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Role</th>
                            @foreach ($domains as $domain)
                                <th scope="col">{{ $domain }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $role)
                            <tr>
                                <td><strong>{{ $role['name'] }}</strong></td>
                                @foreach (array_keys($domains) as $key)
                                    <td class="cell-tight">
                                        @if (($role['ranks'][$key] ?? 0) > 0)
                                            {{ $role['ranks'][$key] }}
                                        @else
                                            <span class="dash-quiet-meta">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
