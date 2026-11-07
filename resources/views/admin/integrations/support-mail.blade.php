{{--
    Email to ticket (owner, 2026-11-06). The client secret and the sign-in token
    are never put on this page: only whether they are there.
--}}
<section class="card" id="support-mail">
    <div class="card-hd">
        <span class="card-title">Support mailbox: email to ticket</span>
        @if ($mailbox?->isConnected())
            <span class="pill pill-green">Connected</span>
        @else
            <span class="pill pill-amber">Not connected</span>
        @endif
    </div>

    <div class="card-body">
        @if (! $mailboxReady)
            @include('partials.notice', [
                'tone' => 'warning',
                'title' => 'Not switched on yet',
                'message' => 'Apply the pending changes in Admin > Updates first.',
            ])
        @else
            <p class="dash-note">
                Mail to the support address becomes a ticket: from a client, a client ticket with
                "Ticket created" and its reference sent back; from an employee, their ticket; from
                anybody else, a ticket for Support and the automated reply. A reply with the
                reference is added to its ticket. Read-only: this never sends, moves or deletes mail
                in the mailbox.
            </p>

            @if ($mailbox->isConnected())
                <div class="stat-row">
                    <span class="stat-label">Reading</span>
                    <span class="stat-value">Mail to {{ $mailbox->address }}, in {{ $mailbox->account_email }}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Connected</span>
                    <span class="stat-value">{{ $mailbox->connected_at?->format('d M Y, g:i A') }}{{ $mailbox->connectedBy ? ' by '.$mailbox->connectedBy->name : '' }}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Last checked</span>
                    <span class="stat-value">{{ $mailbox->last_checked_at?->format('d M Y, g:i A') ?? 'Not yet. Set up the Cron Job, or press Check now.' }}</span>
                </div>

                @if ($mailbox->last_error)
                    @include('partials.notice', ['tone' => 'danger', 'title' => 'The last check failed', 'message' => $mailbox->last_error])
                @endif

                <div class="hd-actions">
                    <form method="POST" action="{{ route('admin.integrations.support-mail.check') }}">
                        @csrf
                        <button class="btn btn-primary" type="submit">Check now</button>
                    </form>
                    <form method="POST" action="{{ route('admin.integrations.support-mail.disconnect') }}">
                        @csrf
                        <button class="btn btn-outline" type="submit">Disconnect</button>
                    </form>
                </div>
            @elseif ($mailbox->hasClient())
                @include('partials.notice', [
                    'tone' => 'info',
                    'title' => 'One step left',
                    'message' => 'Sign in with Google as '.$mailbox->account_email.'. It asks only to read mail.',
                ])
                <div class="hd-actions">
                    <a class="btn btn-primary" href="{{ route('admin.integrations.support-mail.connect') }}">Sign in with Google</a>
                </div>
            @endif

            <form class="pay-form" method="POST" action="{{ route('admin.integrations.support-mail.save') }}">
                @csrf

                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-field-lbl" for="sm-address">Support address</label>
                        <input id="sm-address" name="address" type="email" required maxlength="190"
                               value="{{ old('address', $mailbox->address ?? 'support@zephryxlabs.in') }}">
                        @error('address') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-field">
                        <label class="form-field-lbl" for="sm-account">Google account it arrives in</label>
                        <input id="sm-account" name="account_email" type="email" required maxlength="190"
                               value="{{ old('account_email', $mailbox->account_email ?? 'contact@zephryxlabs.in') }}">
                        @error('account_email') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-field">
                        <label class="form-field-lbl" for="sm-client">OAuth client ID</label>
                        <input id="sm-client" name="client_id" type="text" required maxlength="255"
                               value="{{ old('client_id', $mailbox->client_id) }}">
                        @error('client_id') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-field">
                        <label class="form-field-lbl" for="sm-secret">OAuth client secret</label>
                        <input id="sm-secret" name="client_secret" type="password" maxlength="255" autocomplete="off"
                               placeholder="{{ $mailbox->hasClient() ? 'Saved. Leave blank to keep it.' : '' }}">
                        @error('client_secret') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-field an-form-wide">
                        <label class="form-field-lbl" for="sm-redirect">Authorised redirect URI (paste this into Google Cloud)</label>
                        <input id="sm-redirect" type="text" readonly value="{{ $mailboxCallback }}">
                    </div>

                    <div class="form-field an-form-wide">
                        <label class="form-field-lbl" for="sm-keywords">Category keywords</label>
                        <textarea id="sm-keywords" name="keywords" rows="8" maxlength="5000">{{ old('keywords', $mailbox->keywords ?? \App\Models\SupportMailbox::DEFAULT_KEYWORDS) }}</textarea>
                        <span class="prose-quiet">One category per line: "Category: word, word". The category with the most words found in the email is chosen. A category not in Master Data is skipped.</span>
                        @error('keywords') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-outline" type="submit">Save</button>
                </div>
            </form>

            @if ($recentMail->isNotEmpty())
                <div class="card-hd">
                    <span class="card-title">Last 10 messages</span>
                </div>
                @foreach ($recentMail as $message)
                    <div class="stat-row">
                        <span class="stat-label">{{ \Illuminate\Support\Carbon::parse($message->created_at)->format('d M, g:i A') }} · {{ $message->from_email }}</span>
                        <span class="stat-value">
                            {{ ucfirst($message->outcome) }}{{ $message->ticket_reference ? ' '.$message->ticket_reference : '' }}{{ $message->note ? ' ('.$message->note.')' : '' }}
                        </span>
                    </div>
                @endforeach
            @endif
        @endif
    </div>
</section>
