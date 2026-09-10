@extends('layouts.app')

@section('title', $meta['label'])

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('admin.master.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Master data
            </a>
            <h1>{{ $meta['label'] }}</h1>
            <p>{{ $meta['note'] }}</p>
        </div>
    </div>

    <section class="dash-grid">
        <div class="dash-main">
            <section class="card table-card">
                <div class="card-hd">
                    <span class="card-title">{{ $meta['label'] }}</span>
                </div>

                @if ($rows->isEmpty())
                    <div class="card-body">
                        <p class="rail-empty">Nothing in this list yet.</p>
                    </div>
                @else
                    <div class="card-body-table">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Code</th>
                                    <th scope="col">In use</th>
                                    <th scope="col">Status</th>
                                    <th scope="col"><span class="sr-only">Retire</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td><strong>{{ $row['name'] }}</strong></td>
                                        <td class="cell-tight"><code>{{ $row['code'] }}</code></td>
                                        <td class="cell-tight">
                                            {{--
                                                Counted from the records
                                                themselves, not stored, so this
                                                number cannot disagree with the
                                                module behind it — and it is the
                                                number that makes deactivating
                                                something a real decision rather
                                                than a click.
                                            --}}
                                            @if ($row['in_use'] === null)
                                                {{-- Null is not zero. "Nothing
                                                     uses it" would turn "nobody
                                                     counted" into a
                                                     reassurance. --}}
                                                <span class="dash-quiet-meta">Not counted</span>
                                            @elseif ($row['in_use'] > 0)
                                                {{ $row['in_use'] }} {{ $row['in_use'] === 1 ? $meta['unit'] : $meta['unit'].'s' }}
                                            @else
                                                <span class="dash-quiet-meta">Nothing uses it</span>
                                            @endif
                                        </td>
                                        <td class="cell-tight">
                                            @if ($row['active'])
                                                <span class="pill pill-green">Active</span>
                                            @else
                                                <span class="pill pill-gray">Retired</span>
                                            @endif
                                        </td>
                                        <td class="cell-tight">
                                            @if ($row['active'])
                                                <form method="POST" action="{{ route('admin.master.deactivate', $list) }}">
                                                    @csrf
                                                    <input type="hidden" name="item" value="{{ $row['id'] }}">
                                                    {{-- "Retire", not "Delete".
                                                         The word is the rule:
                                                         nothing here removes a
                                                         row. --}}
                                                    <button class="btn btn-outline btn-sm" type="submit">Retire</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="card">
                <div class="card-hd">
                    <span class="card-title">Add to this list</span>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('admin.master.store', $list) }}">
                        @csrf

                        <div class="form-grid">
                            <div class="form-field">
                                <label class="form-field-lbl" for="md-name">Name</label>
                                <input id="md-name" name="name" type="text" maxlength="120" required
                                       value="{{ old('name') }}">
                            </div>

                            <div class="form-field">
                                <label class="form-field-lbl" for="md-code">Code</label>
                                <input id="md-code" name="code" type="text" maxlength="16" required
                                       value="{{ old('code') }}">
                                <span class="pay-hint">
                                    Short, and permanent — other records will reference it.
                                    {{-- Said here because it is the one
                                         behaviour somebody would not guess: a
                                         code that was retired comes back rather
                                         than being duplicated. --}}
                                    A code that was retired earlier is brought back rather than added twice.
                                </span>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit">Add</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Retiring a row</strong>
                </div>

                {{--
                    The whole rule, in the place somebody reads it before
                    looking for a delete button that is not there.
                --}}
                <p class="dash-note">
                    There is no delete. A row here is referenced by records that already
                    exist, and removing it would leave those records pointing at nothing —
                    a blank cell nobody can ever resolve.
                </p>

                <p class="dash-note">
                    Retiring one stops it being offered for anything new. Everything that
                    already uses it keeps working, and keeps reading correctly.
                </p>

                <div class="stat-row">
                    <span class="stat-label">Records using this list</span>
                    <span class="stat-value">
                        {{-- Null where nothing counts against this list at all.
                             Printed as "0" it would read as "safe to retire
                             anything here", which is a claim nobody checked. --}}
                        {{ $inUse === null ? 'Not counted' : $inUse }}
                    </span>
                </div>

                @if ($inUse === null)
                    <p class="dash-note">
                        Nothing in the application points at this list yet, so no count can be
                        offered. Retiring a row here changes what is offered and nothing else.
                    </p>
                @endif
            </section>
        </aside>
    </section>
@endsection
