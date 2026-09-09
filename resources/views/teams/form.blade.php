@extends('layouts.app')

@php
    use App\Models\Team as TeamModel;
    use App\Support\TeamPresenter as P;

    // One template for creating and for editing — the fields and their
    // validation are identical, and two files would mean every change made
    // twice, with the second one eventually forgotten.
    $editing = $team !== null;
@endphp

@section('title', $editing ? 'Edit '.$team->name : 'Create a team')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ $editing ? route('teams.show', ['team' => $team->reference]) : route('teams.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                {{ $editing ? $team->name : 'Teams' }}
            </a>
            <h1>{{ $editing ? 'Edit this team' : 'Create a team' }}</h1>
            <p>
                @if ($editing)
                    Changes are recorded against this team, with who made them.
                @else
                    Members are added afterwards, on the team's own page.
                @endif
            </p>
        </div>
    </div>

    <form class="an-form" method="POST"
          action="{{ $editing ? route('teams.update', ['team' => $team->reference]) : route('teams.store') }}">
        @csrf

        <section class="an-form-grid">
            <div class="an-form-main">
                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="8" r="3"/><circle cx="5" cy="17" r="2.5"/><circle cx="19" cy="17" r="2.5"/>
                            <path d="M12 11v3M12 14l-5 1M12 14l5 1"/>
                        </svg>
                        The team
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="team-name">Name</label>
                            <input id="team-name" name="name" type="text" required
                                   value="{{ old('name', $team?->name) }}"
                                   @if ($errors->has('name')) aria-invalid="true" aria-describedby="team-name-error" @endif>
                            @error('name')
                                <span class="field-error" id="team-name-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="team-reference">Team ID</label>
                            {{-- Shown, never typed. Derived from the highest
                                 existing one so it can never be reissued. --}}
                            <input id="team-reference" type="text" value="{{ $reference }}" disabled>
                            <span class="pay-hint">Assigned automatically and never reused.</span>
                        </div>

                        <div class="form-field an-form-wide">
                            <label class="form-field-lbl" for="team-purpose">Purpose <span class="an-optional">(optional)</span></label>
                            <input id="team-purpose" name="purpose" type="text"
                                   value="{{ old('purpose', $team?->purpose) }}">
                            <span class="pay-hint">One line. It is what the list shows under the name.</span>
                            @error('purpose')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="team-lead">Team lead <span class="an-optional">(optional)</span></label>
                            <select id="team-lead" name="lead_id">
                                <option value="">No lead assigned</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}"
                                        @selected((int) old('lead_id', $team?->lead_id) === $employee->id)>
                                        {{ $employee->user?->name }} ({{ $employee->user?->user_id }})
                                    </option>
                                @endforeach
                            </select>
                            <span class="pay-hint">They are added to the team as a member as well.</span>
                            @error('lead_id')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="team-status">Status</label>
                            <select id="team-status" name="status" required>
                                @foreach (TeamModel::STATUSES as $value)
                                    <option value="{{ $value }}" @selected(old('status', $team?->status ?? 'active') === $value)>
                                        {{ P::status($value)['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="pay-hint">None of these means deleted. Tasks and projects point at teams.</span>
                            @error('status')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="team-formed">Formed on <span class="an-optional">(optional)</span></label>
                            <input id="team-formed" name="formed_on" type="date"
                                   max="{{ now()->toDateString() }}"
                                   value="{{ old('formed_on', $team?->formed_on?->toDateString()) }}">
                            <span class="pay-hint">When the team started, which is not when this record was made.</span>
                            @error('formed_on')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <aside class="rail">
                <section class="rail-card">
                    <div class="rail-hd">
                        <strong>{{ $editing ? 'What changes' : 'What happens next' }}</strong>
                    </div>

                    <div class="prose">
                        @if ($editing)
                            <p>
                                The team is updated and the change is written to the audit log
                                with your name against it. Its ID does not change, and nobody
                                is added to or removed from it here.
                            </p>
                        @else
                            <p>
                                The team is created with its lead as its first member. Everybody
                                else is added on the team's own page, which is also where a Team
                                Lead manages their own.
                            </p>
                        @endif
                    </div>

                    <button class="btn btn-primary" type="submit">
                        {{ $editing ? 'Save changes' : 'Create team' }}
                    </button>

                    <a class="btn btn-outline btn-sm"
                       href="{{ $editing ? route('teams.show', ['team' => $team->reference]) : route('teams.index') }}">
                        Cancel
                    </a>
                </section>
            </aside>
        </section>
    </form>
@endsection
