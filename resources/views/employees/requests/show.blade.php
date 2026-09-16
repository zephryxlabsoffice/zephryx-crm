@extends('layouts.app')

@section('title', 'Change request — '.$name)

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('employees.requests.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Change requests
            </a>
            <h1>{{ $name }}</h1>
            <p>{{ $staffId }} · sent {{ $request->created_at?->diffForHumans() }}</p>
        </div>

        <a class="btn btn-outline" href="{{ route('employees.show', ['employee' => $staffId]) }}">
            Open their record
        </a>
    </div>

    @unless ($request->isPending())
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'This has already been decided',
            'message' => 'It was '.$request->outcome().'. Nothing on this page will change it — decisions are kept, not reopened.',
        ])
    @endunless

    @if ($mine)
        @include('partials.notice', [
            'tone' => 'warning',
            'title' => 'This is your own request',
            'message' => 'Somebody else has to decide it. The same rule as nobody approving their own leave: '
                .'a control you can apply to yourself is not a control.',
        ])
    @endif

    <section class="att-detail-grid">
        <div class="att-main">
            <div class="card">
                <div class="section-hd">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h10"/>
                    </svg>
                    What they are asking for
                </div>

                <dl class="field-grid att-record-grid">
                    @foreach ($rows as $row)
                        <div class="lv-field">
                            <dt class="lv-field-lbl">{{ $row['label'] }}</dt>
                            <dd>
                                {{-- Both halves. Deciding needs the comparison,
                                     not the new value on its own — the common
                                     case is a typo somebody is correcting, and
                                     only the pair shows that. --}}
                                <span class="an-optional">{{ $row['was'] }}</span>
                                →
                                <strong>{{ $row['now'] }}</strong>
                            </dd>
                        </div>
                    @endforeach

                    @if ($hasPhoto)
                        <div class="lv-field">
                            <dt class="lv-field-lbl">{{ \App\Support\ProfilePolicy::labelOf('photo') }}</dt>
                            <dd>A new photo, waiting to replace the current one</dd>
                        </div>
                    @endif
                </dl>

                <div class="prose prose-quiet">
                    <p>
                        {{-- The point of the whole flow, on the screen where the
                             decision is made. --}}
                        Applying this moves their record. Check it against the document they
                        brought in first — that is what this step is for.
                    </p>
                </div>
            </div>
        </div>

        <aside class="rail">
            @if ($request->isPending() && ! $mine)
                <section class="rail-card">
                    <div class="rail-hd"><strong>Apply it</strong></div>

                    <div class="prose">
                        <p>Their record moves now, and the change is written to the audit log with your name against it.</p>
                    </div>

                    <form method="POST" action="{{ route('employees.requests.apply', ['profileRequest' => $request->id]) }}">
                        @csrf
                        <button class="btn btn-primary" type="submit">Apply to the record</button>
                    </form>
                </section>

                <section class="rail-card">
                    <div class="rail-hd"><strong>Decline it</strong></div>

                    <div class="prose">
                        <p>
                            They see the reason on their profile, so write something they can act
                            on — which document is missing, or what to bring in.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('employees.requests.reject', ['profileRequest' => $request->id]) }}">
                        @csrf
                        <div class="form-field">
                            <label class="form-field-lbl" for="req-reason">Reason</label>
                            <textarea id="req-reason" name="reason" rows="3" maxlength="300" required
                                      placeholder="No proof of address has been handed in yet."
                                      @if ($errors->has('reason')) aria-invalid="true" aria-describedby="req-reason-error" @endif></textarea>
                            @error('reason')
                                <span class="field-error" id="req-reason-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <button class="btn btn-outline" type="submit">Decline</button>
                    </form>
                </section>
            @elseif (! $request->isPending())
                <section class="rail-card">
                    <div class="rail-hd"><strong>Decision</strong></div>

                    <div class="stat-row">
                        <span class="stat-label">{{ $request->updated_at?->format('d M Y') }}</span>
                        <span class="stat-value">{{ ucfirst($request->outcome()) }}</span>
                    </div>

                    @if ($request->decider)
                        <p class="att-rail-note">by {{ $request->decider->name }}</p>
                    @endif

                    @if ($request->decision_note)
                        <p class="att-rail-note">{{ $request->decision_note }}</p>
                    @endif
                </section>
            @endif
        </aside>
    </section>
@endsection
