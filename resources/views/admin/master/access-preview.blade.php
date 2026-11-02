@extends('layouts.app')

@section('title', 'Check '.$item->name)

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('admin.master.access', $item->id) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Back without saving
            </a>
            <h1>Check {{ $item->name }} before saving</h1>
            <p>
                Carries: {{ $proposed['roles'] === [] ? 'no roles' : collect($proposed['roles'])->map(fn ($key) => $roleNames[$key] ?? $key)->join(', ') }}.
                Nothing has been saved yet.
            </p>
        </div>
    </div>

    <section class="card">
        <div class="card-hd">
            <span class="card-title">Who this changes</span>
        </div>

        <div class="card-body">
            @if ($changes === [])
                <p class="dash-note">Nobody holds this designation yet, so this changes nobody today.</p>
            @endif

            @foreach ($changes as $person)
                <div>
                    <p class="dash-note"><strong>{{ $person['name'] }}</strong></p>

                    @if ($person['gains'] === [] && $person['loses'] === [])
                        <p class="dash-note">No change.</p>
                    @endif

                    @if ($person['gains'] !== [])
                        <p class="dash-note"><span class="pill pill-green">Gains</span>
                            {{ collect($person['gains'])->map(fn ($key) => $labels[$key] ?? $key)->join(', ') }}</p>
                    @endif

                    @if ($person['loses'] !== [])
                        <p class="dash-note"><span class="pill pill-gray">Loses</span>
                            {{ collect($person['loses'])->map(fn ($key) => $labels[$key] ?? $key)->join(', ') }}</p>
                    @endif

                    @if ($person['still_held'] !== [])
                        <p class="dash-note">Never does not apply to them for
                            {{ collect($person['still_held'])->map(fn ($key) => $labels[$key] ?? $key)->join(', ') }},
                            because a role given directly to them includes it. Remove that role on their account if it should stop.</p>
                    @endif

                    @if ($person['redundant_roles'] !== [])
                        <p class="dash-note">{{ collect($person['redundant_roles'])->map(fn ($key) => $roleNames[$key] ?? $key)->join(', ') }}
                            is also given to them directly and is no longer needed. It can be removed on their account.</p>
                    @endif
                </div>
            @endforeach

            <form method="POST" action="{{ route('admin.master.access.update', $item->id) }}">
                @csrf

                @foreach ($proposed['roles'] as $role)
                    <input type="hidden" name="roles[]" value="{{ $role }}">
                @endforeach

                @foreach ($proposed['exceptions'] as $key => $effect)
                    <input type="hidden" name="exceptions[{{ $key }}]" value="{{ $effect }}">
                @endforeach

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Save {{ $item->name }}</button>
                </div>
            </form>
        </div>
    </section>
@endsection
