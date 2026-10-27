@extends('layouts.app')

@section('title', 'New role')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('admin.access.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Access control
            </a>
            <h1>New role</h1>
            <p>Name it, pick its level, and tick what it may do.</p>
        </div>
    </div>

    {{-- A GET form of its own: choosing a starting role reloads the page with
         that role's permissions ticked. No script — CSP — just data-auto-submit. --}}
    <form class="card" method="GET" action="{{ route('admin.access.create') }}">
        <div class="card-body">
            <div class="form-field">
                <label class="form-field-lbl" for="role-from">Start from an existing role (optional)</label>
                <select id="role-from" name="from" data-auto-submit>
                    <option value="">Nothing ticked</option>
                    @foreach ($roles as $key => $role)
                        <option value="{{ $key }}" @selected(($from['key'] ?? null) === $key)>{{ $role['name'] }}</option>
                    @endforeach
                </select>
                <span class="pay-hint">Copies its permissions below as a starting point — change any of them before saving.</span>
                <noscript><button class="btn btn-outline btn-sm" type="submit">Use this role</button></noscript>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.access.store') }}">
        @csrf

        <section class="dash-grid">
            <div class="dash-main">
                <section class="card">
                    <div class="card-hd">
                        <span class="card-title">Name it</span>
                    </div>

                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-field">
                                <label class="form-field-lbl" for="role-name">Name</label>
                                <input id="role-name" name="role_name" type="text" required maxlength="100"
                                       placeholder="Support Lead" value="{{ old('role_name') }}">
                                @error('role_name') <span class="field-error">{{ $message }}</span> @enderror
                                {{-- The key is made from the name; its errors read as the name's. --}}
                                @error('role_key') <span class="field-error">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-field an-form-wide">
                                <label class="form-field-lbl" for="role-description">Description</label>
                                <input id="role-description" name="description" type="text" maxlength="500"
                                       placeholder="Second-line triage on the busiest ticket queues." value="{{ old('description') }}">
                                @error('description') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </section>

                @foreach ($permissions as $module => $entries)
                    <section class="card">
                        <div class="card-hd">
                            <span class="card-title">{{ $module }}</span>
                        </div>

                        <div class="card-body">
                            <div class="ad-perms">
                                @foreach ($entries as $entry)
                                    <label class="ad-perm">
                                        <input type="checkbox" name="permissions[]" value="{{ $entry['key'] }}"
                                               @checked(in_array($entry['key'], old('permissions', $ticked), true))>

                                        <span class="ad-perm-body">
                                            <span class="ad-perm-head">
                                                <strong>{{ $entry['label'] }}</strong>
                                                <code>{{ $entry['key'] }}</code>
                                            </span>

                                            @if ($entry['note'])
                                                <span class="ad-perm-note">{{ $entry['note'] }}</span>
                                            @endif
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
                        <strong>Level</strong>
                    </div>

                    <div class="form-field">
                        <label class="form-field-lbl" for="role-level">Same level as</label>
                        <select id="role-level" name="level_as">
                            <option value="">Lowest — acts on nobody</option>
                            @foreach ($roles as $key => $role)
                                <option value="{{ $key }}" @selected(old('level_as', $from['key'] ?? 'employee') === $key)>{{ $role['name'] }}</option>
                            @endforeach
                        </select>
                        @error('level_as') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <p class="dash-note">
                        Level decides who this role may act on and where approvals go,
                        for example a Team Lead approving a team member's leave. It never
                        grants a permission on its own. Pick the existing role this one
                        sits beside.
                    </p>
                </section>

                <section class="rail-card">
                    <div class="dash-punch-action">
                        <button class="btn btn-primary" type="submit">Create role</button>
                    </div>

                    <p class="dash-note">
                        Nobody holds it yet — assign it to somebody from their account
                        page once it exists.
                    </p>
                </section>
            </aside>
        </section>
    </form>
@endsection
