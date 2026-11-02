@extends('layouts.app')

@section('title', 'Access for '.$item->name)

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('admin.master.show', 'designations') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Designations
            </a>
            <h1>Access for {{ $item->name }}</h1>
            <p>{{ $holders }} {{ $holders === 1 ? 'person holds' : 'people hold' }} this designation. Nothing changes until you have seen who gains and loses what, and confirmed it.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.master.access.preview', $item->id) }}">
        @csrf

        <section class="card">
            <div class="card-hd">
                <span class="card-title">Roles it carries</span>
            </div>

            <div class="card-body">
                <p class="ad-group-note">Everybody holding this designation gets these roles' access and rank, on top of what every employee has.</p>

                <div class="ad-perms">
                    @foreach ($roles as $role)
                        <label class="ad-perm">
                            <input type="checkbox" name="roles[]" value="{{ $role->role_key }}"
                                   @checked(in_array($role->role_key, $settings['roles'], true))>
                            <span class="ad-perm-body">
                                <span class="ad-perm-head"><strong>{{ $role->role_name }}</strong></span>
                                <span class="ad-perm-note">{{ $role->description }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-hd">
                <span class="card-title">Exceptions</span>
            </div>

            <div class="card-body">
                <p class="ad-group-note">Leave these on Inherit unless this job must differ from its roles. Never only removes what this designation gives: it cannot touch a person's own payslip or leave, or a role given to them directly.</p>

                @foreach ($permissions as $module => $entries)
                    <details @if (collect($entries)->contains(fn ($entry) => isset($settings['exceptions'][$entry['key']]))) open @endif>
                        <summary><strong>{{ $module }}</strong></summary>

                        <div class="form-grid">
                            @foreach ($entries as $entry)
                                <div class="form-field">
                                    <label class="form-field-lbl" for="ex-{{ $entry['key'] }}">
                                        {{ $entry['label'] }}
                                        @if (in_array($entry['key'], $sensitive, true))
                                            <span class="pill pill-gray">Sensitive</span>
                                        @endif
                                    </label>
                                    <select id="ex-{{ $entry['key'] }}" name="exceptions[{{ $entry['key'] }}]">
                                        <option value="inherit">Inherit ({{ in_array($entry['key'], $inherited, true) ? 'allowed' : 'not allowed' }})</option>
                                        <option value="allow" @selected(($settings['exceptions'][$entry['key']] ?? null) === 'allow')>Always allow</option>
                                        <option value="never" @selected(($settings['exceptions'][$entry['key']] ?? null) === 'never')>Never</option>
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endforeach

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Preview changes</button>
                </div>
            </div>
        </section>
    </form>
@endsection
