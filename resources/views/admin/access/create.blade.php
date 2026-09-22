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
            <p>The same screen editing a role uses, with nothing ticked yet.</p>
        </div>
    </div>

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
                            </div>

                            <div class="form-field">
                                <label class="form-field-lbl" for="role-key">Key</label>
                                <input id="role-key" name="role_key" type="text" required maxlength="32"
                                       pattern="[a-z_]+" placeholder="support_lead" value="{{ old('role_key') }}">
                                <span class="pay-hint">Lowercase letters and underscores only. Cannot be changed after this.</span>
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
                                               @checked(in_array($entry['key'], old('permissions', []), true))>

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
                        <strong>Rank</strong>
                    </div>

                    <p class="dash-note">
                        Rank never grants a permission (§2.5) — it only answers whether
                        somebody holding this role may act on somebody else, and which
                        way an approval routes. 0 means no standing in that domain.
                    </p>

                    @foreach ($domains as $key => $label)
                        <div class="form-field">
                            <label class="form-field-lbl" for="rank-{{ $key }}">{{ $label }}</label>
                            <input id="rank-{{ $key }}" name="ranks[{{ $key }}]" type="number" min="0" max="100"
                                   value="{{ old('ranks.'.$key, 0) }}">
                        </div>
                    @endforeach
                    @error('ranks') <span class="field-error">{{ $message }}</span> @enderror
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
