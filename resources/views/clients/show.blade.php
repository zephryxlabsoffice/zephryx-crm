@extends('layouts.app')

@php
    use App\Models\Client as ClientModel;
    use App\Support\Avatar;
    use App\Support\ClientPresenter as P;

    $pill = P::status($record->status);
@endphp

@section('title', $record->name)

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('clients.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Clients
            </a>
            <h1>{{ $record->name }}</h1>
            <p>{{ $record->reference }}{{ $record->industry ? ' · '.$record->industry : '' }}</p>
        </div>

        @if ($mayEdit)
            <a class="btn btn-outline" href="{{ route('clients.edit', ['client' => $record->reference]) }}">
                Edit record
            </a>
        @endif
    </div>

    <section class="att-detail-grid">
        <div class="att-main">
            <div class="card">
                <div class="att-record-hd">
                    <div class="person-row">
                        <span class="avatar {{ Avatar::tint($record->name) }}" aria-hidden="true">{{ Avatar::letter($record->name) }}</span>
                        <span class="person-body">
                            <strong>{{ $record->name }}</strong>
                            <span>{{ $record->industry ?: 'No industry recorded' }}</span>
                        </span>
                    </div>
                    <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                </div>

                <dl class="field-grid att-record-grid">
                    <div class="lv-field">
                        <dt class="lv-field-lbl">Reference</dt>
                        <dd>{{ $record->reference }}</dd>
                    </div>

                    <div class="lv-field">
                        <dt class="lv-field-lbl">Account manager</dt>
                        <dd>{{ $record->accountManager?->name ?: 'Not assigned' }}</dd>
                    </div>

                    <div class="lv-field">
                        <dt class="lv-field-lbl">Contact</dt>
                        <dd>{{ $record->contact_name ?: 'Not recorded' }}</dd>
                    </div>

                    <div class="lv-field">
                        <dt class="lv-field-lbl">Contact email</dt>
                        <dd>
                            @if ($record->contact_email)
                                <a class="emp-email" href="mailto:{{ $record->contact_email }}">{{ $record->contact_email }}</a>
                            @else
                                Not recorded
                            @endif
                        </dd>
                    </div>

                    <div class="lv-field">
                        <dt class="lv-field-lbl">Contact phone</dt>
                        <dd>{{ $record->contact_phone ?: 'Not recorded' }}</dd>
                    </div>

                    <div class="lv-field">
                        <dt class="lv-field-lbl">Signed on</dt>
                        <dd>{{ $record->signed_on?->format('d M Y') ?: 'Not recorded' }}</dd>
                    </div>
                </dl>

                @if ($record->notes)
                    <div class="prose">
                        <p>{{ $record->notes }}</p>
                    </div>
                @endif
            </div>

            <div class="card">
                <div class="section-hd">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    </svg>
                    Portal access
                </div>

                <div class="prose">
                    <p>
                        Everybody here can sign in and read this client's own projects,
                        invoices, tickets and meetings — and nothing belonging to anybody
                        else. Access is scoped by the reference above, not by the name.
                    </p>
                </div>

                @forelse ($accounts as $account)
                    <div class="stat-row">
                        <span class="stat-label">{{ $account->name }} · {{ $account->email }}</span>
                        <span class="stat-value">{{ $account->user_id }}{{ $account->isActive() ? '' : ' · cannot sign in' }}</span>
                    </div>
                @empty
                    <p class="att-rail-note">Nobody at this client can sign in yet.</p>
                @endforelse

                @if ($mayInvite)
                    <form method="POST" action="{{ route('clients.invite', ['client' => $record->reference]) }}">
                        @csrf

                        <div class="form-grid">
                            <div class="form-field">
                                <label class="form-field-lbl" for="cl-invite-name">Their name</label>
                                <input id="cl-invite-name" name="name" type="text" required
                                       value="{{ old('name', $record->contact_name) }}">
                                @error('name')
                                    <span class="field-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-field">
                                <label class="form-field-lbl" for="cl-invite-email">Their email</label>
                                <input id="cl-invite-email" name="email" type="email" required
                                       autocapitalize="none" spellcheck="false"
                                       value="{{ old('email', $record->contact_email) }}">
                                <span class="pay-hint">This is what they sign in with, so it has to be theirs alone.</span>
                                @error('email')
                                    <span class="field-error">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <button class="btn btn-primary" type="submit">Send an invitation</button>
                    </form>
                @endif
            </div>

            @if ($mayEdit)
                <div class="card att-reject">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 2v20"/><path d="M2 12h20"/>
                        </svg>
                        Move this engagement
                    </div>

                    <div class="prose">
                        <p>
                            The status of the work, not of anybody's login. Marking a client
                            completed keeps every invoice, ticket and project exactly where it
                            is — which is why there is no delete on this page at all.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('clients.status', ['client' => $record->reference]) }}">
                        @csrf

                        <div class="form-field">
                            <label class="form-field-lbl" for="cl-new-status">New status</label>
                            <select id="cl-new-status" name="status" required>
                                @foreach (ClientModel::STATUSES as $value)
                                    <option value="{{ $value }}" @selected($record->status === $value)>
                                        {{ P::status($value)['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <button class="btn btn-outline" type="submit">Update status</button>
                    </form>
                </div>
            @endif
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd">
                    <strong>History</strong>
                </div>

                {{--
                    Everything that has happened to this client, from the audit
                    log (§6). Empty for a client added before the log existed,
                    which is honest: it says nothing has been recorded, not that
                    nothing happened.
                --}}
                @forelse ($history as $entry)
                    <div class="stat-row">
                        <span class="stat-label">{{ $entry->at->format('d M Y') }}</span>
                        <span class="stat-value">{{ $entry->action }}</span>
                    </div>
                    @if ($entry->after_summary)
                        <p class="att-rail-note">
                            {{ $entry->after_summary }}
                            <span class="an-optional">— {{ $entry->actor_label }}</span>
                        </p>
                    @endif
                @empty
                    <p class="att-rail-note">Nothing has been recorded against this client yet.</p>
                @endforelse
            </section>
        </aside>
    </section>
@endsection
