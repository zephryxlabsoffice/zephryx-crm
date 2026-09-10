@extends('layouts.app')

@php use App\Support\Avatar; @endphp

@section('title', $role['name'])

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('admin.access.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Access control
            </a>
            <h1>{{ $role['name'] }}</h1>
            <p>{{ $role['description'] }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.access.update', $role['key']) }}">
        @csrf

        <section class="dash-grid">
            <div class="dash-main">
                @foreach ($permissions as $module => $entries)
                    <section class="card">
                        <div class="card-hd">
                            <span class="card-title">{{ $module }}</span>
                        </div>

                        <div class="card-body">
                            <div class="ad-perms">
                                @foreach ($entries as $entry)
                                    @php
                                        $held = in_array($entry['key'], $role['permissions'], true);
                                        $affects = $impact[$entry['key']] ?? collect();
                                        $isSensitive = in_array($entry['key'], $sensitive, true);
                                    @endphp

                                    <label class="ad-perm @if ($isSensitive) is-sensitive @endif">
                                        <input type="checkbox" name="permissions[]" value="{{ $entry['key'] }}"
                                               @checked($held)>

                                        <span class="ad-perm-body">
                                            <span class="ad-perm-head">
                                                <strong>{{ $entry['label'] }}</strong>
                                                <code>{{ $entry['key'] }}</code>
                                                @if ($isSensitive)
                                                    <span class="pill pill-red">Sensitive</span>
                                                @endif
                                            </span>

                                            @if ($entry['note'])
                                                <span class="ad-perm-note">{{ $entry['note'] }}</span>
                                            @endif

                                            {{--
                                                ─────────────────────────────────────
                                                THE BLAST RADIUS

                                                The whole reason this screen is not a
                                                bare matrix. The click is made looking
                                                at a role name; the consequence lands
                                                on people whose names are not on the
                                                page unless something puts them there.

                                                People who already hold the permission
                                                through another role are excluded —
                                                roles stack as a union (§2.4), so they
                                                would gain nothing and counting them
                                                overstates the change.
                                                ─────────────────────────────────────
                                            --}}
                                            <span class="ad-perm-impact">
                                                @if ($affects->isEmpty())
                                                    @if ($role['holders']->isEmpty())
                                                        Nobody holds this role, so nothing changes yet.
                                                    @else
                                                        Everyone with this role already has it another way.
                                                    @endif
                                                @else
                                                    {{ $held ? 'Revoking takes it from' : 'Granting gives it to' }}
                                                    <strong>{{ $affects->count() === 1 ? '1 person' : $affects->count().' people' }}</strong>:
                                                    {{ $affects->pluck('name')->join(', ', ' and ') }}
                                                @endif
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>

            <aside class="rail">
                <section class="rail-card">
                    <div class="rail-hd">
                        <strong>Who holds this role</strong>
                        <span class="dash-quiet-meta">{{ $role['holders']->count() }}</span>
                    </div>

                    @if ($role['holders']->isEmpty())
                        <p class="rail-empty">Nobody. Changes here affect no one today.</p>
                    @else
                        <div class="rail-list">
                            @foreach ($role['holders'] as $person)
                                <a class="rail-row" href="{{ route('admin.accounts.show', $person['user_id']) }}">
                                    <span class="avatar {{ Avatar::tint($person['name']) }}" aria-hidden="true">
                                        {{ Avatar::initials($person['name']) }}
                                    </span>
                                    <div class="rail-body">
                                        <strong>{{ $person['name'] }}</strong>
                                        <span>{{ $person['designation'] }}</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="rail-card">
                    <div class="rail-hd">
                        <strong>Rank</strong>
                    </div>

                    @foreach ($domains as $key => $label)
                        <div class="stat-row">
                            <span class="stat-label">{{ $label }}</span>
                            <span class="stat-value">
                                @if (($role['ranks'][$key] ?? 0) > 0)
                                    {{ $role['ranks'][$key] }}
                                @else
                                    <span class="stat-value-quiet">No standing</span>
                                @endif
                            </span>
                        </div>
                    @endforeach

                    <p class="dash-note">Rank routes approvals. It grants nothing (§2.5).</p>
                </section>

                <section class="rail-card">
                    <div class="rail-hd">
                        <strong>The Admin Panel</strong>
                    </div>

                    {{--
                        §2.1: not a role, and not assignable. Said here because
                        its absence from the list is otherwise read as an
                        oversight — and because the alternative, a toggle that
                        grants nothing, would be worse than saying nothing.
                    --}}
                    <p class="dash-note">
                        Not on this list, and not on any other. The panel is an account
                        type in its own realm, not authority a role can be given a piece
                        of — there is nothing to grant here.
                    </p>
                </section>

                <section class="rail-card">
                    <div class="rail-hd">
                        <strong>The Employee base</strong>
                    </div>

                    {{--
                        §5: not a role, and not editable — granted implicitly to
                        every staff account of kind `employee` precisely so a
                        role edit cannot take it away. Said here because its
                        absence from the list above is otherwise a puzzle.
                    --}}
                    <p class="dash-note">
                        My attendance, my leave, my payslips, my profile. Every staff
                        account has these, they are not part of any role, and nothing on
                        this page can remove them.
                    </p>
                </section>

                <section class="rail-card">
                    <div class="dash-punch-action">
                        <button class="btn btn-primary" type="submit">Save permissions</button>
                    </div>
                </section>
            </aside>
        </section>
    </form>
@endsection
