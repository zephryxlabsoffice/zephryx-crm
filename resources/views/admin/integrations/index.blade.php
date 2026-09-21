@extends('layouts.app')

@section('title', 'Integrations')

@section('content')
    <div class="page-hd">
        <h1>Integrations</h1>
        <p>
            The one Google connection this company has. Files — payslips, invoices, ticket and task
            attachments — go to a Shared Drive through it. Calendar and Meet will read the same
            connection once that module is built; it is not wired up yet.
        </p>
    </div>

    @if (session('status'))
        @include('partials.notice', [
            'tone' => session('status_tone', 'success'),
            'message' => session('status'),
        ])
    @endif

    <section class="dash-grid">
        <div class="dash-main">
            <section class="card">
                <div class="card-hd">
                    <span class="card-title">{{ $connection->isConnected() ? 'Reconnect' : 'Connect' }} Google</span>
                </div>

                <div class="card-body">
                    {{--
                        Said before the field that matters, not after: by the
                        time somebody has pasted a key and pressed submit is
                        the wrong moment to learn it is shown only once.
                    --}}
                    @include('partials.notice', [
                        'tone' => 'warning',
                        'title' => 'The key is never shown again',
                        'message' => 'Paste the full JSON key file once. Afterwards this screen shows only the '
                            .'service account\'s email, its fingerprint and when it was connected — never the key '
                            .'itself.',
                    ])

                    <form method="POST" action="{{ route('admin.integrations.connect') }}">
                        @csrf

                        <div class="form-field">
                            <label class="form-field-lbl" for="ig-key">Service account key (JSON)</label>
                            <textarea id="ig-key" name="service_account_key" rows="8" required
                                      placeholder="Paste the full contents of the downloaded JSON key file"
                                      @if ($errors->has('service_account_key')) aria-invalid="true" aria-describedby="ig-key-error" @endif
                            >{{ old('service_account_key') }}</textarea>
                            @error('service_account_key')
                                <span class="field-error" id="ig-key-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="ig-drive">Shared Drive ID</label>
                            <input id="ig-drive" name="shared_drive_id" type="text" required
                                   value="{{ old('shared_drive_id', $connection->shared_drive_id) }}">
                            <p class="ad-setting-note">
                                The Shared Drive this service account was added to as a member — not a folder in
                                anybody's personal Drive (decided 2026-09-16: a service account cannot own files
                                on one of those, and the folder leaves with whoever shared it).
                            </p>
                            @error('shared_drive_id')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="ig-cal">Calendar ID</label>
                            <input id="ig-cal" name="calendar_id" type="text"
                                   value="{{ old('calendar_id', $connection->calendar_id) }}">
                            <p class="ad-setting-note">
                                Optional for now — Meetings does not read this yet. Stored here so it is entered
                                once, not twice.
                            </p>
                            @error('calendar_id')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="ig-imp">Impersonate (Calendar only)</label>
                            <input id="ig-imp" name="impersonate_email" type="email"
                                   value="{{ old('impersonate_email', $connection->impersonate_email) }}">
                            <p class="ad-setting-note">
                                The Workspace mailbox every meeting is created under, once Meetings is built. This
                                key needs domain-wide delegation to use it — Drive does not need any.
                            </p>
                            @error('impersonate_email')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit">
                                {{ $connection->isConnected() ? 'Reconnect' : 'Connect' }}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Status</strong>
                    <span class="pill {{ $connection->isConnected() ? 'pill-green' : 'pill-amber' }}">
                        {{ $connection->isConnected() ? 'Connected' : 'Not connected' }}
                    </span>
                </div>

                @if ($connection->isConnected())
                    <div class="stat-row">
                        <span class="stat-label">Service account</span>
                        <span class="stat-value">{{ $connection->service_account_email }}</span>
                    </div>

                    <div class="stat-row">
                        <span class="stat-label">Fingerprint</span>
                        <span class="stat-value"><code>{{ $connection->key_fingerprint }}</code></span>
                    </div>

                    <div class="stat-row">
                        <span class="stat-label">Connected</span>
                        <span class="stat-value">{{ $connection->connected_at?->format('d M Y, H:i') }}</span>
                    </div>

                    @if ($connection->connectedBy)
                        <div class="stat-row">
                            <span class="stat-label">By</span>
                            <span class="stat-value">{{ $connection->connectedBy->name }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.integrations.test') }}">
                        @csrf
                        <p class="dash-note">
                            Creates and deletes a small file in the Shared Drive, so a key that cannot write is
                            caught here rather than the first time somebody uploads a payslip.
                        </p>
                        <div class="dash-punch-action">
                            <button class="btn btn-outline" type="submit">Test connection</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('admin.integrations.disconnect') }}">
                        @csrf
                        <p class="dash-note">
                            Removes the key. The Shared Drive ID, Calendar ID and impersonation address stay on
                            file, so reconnecting with a new key does not mean retyping them.
                        </p>
                        <div class="dash-punch-action">
                            <button class="btn btn-outline" type="submit">Disconnect</button>
                        </div>
                    </form>
                @else
                    <p class="rail-empty">
                        No key on file. Drive uploads and Calendar meetings will not work until one is connected.
                    </p>
                @endif
            </section>
        </aside>
    </section>
@endsection
