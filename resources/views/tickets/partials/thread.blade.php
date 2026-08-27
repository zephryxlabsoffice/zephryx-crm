@php
    use App\Support\Avatar;
    use App\Support\TicketPresenter as P;
    $isClientTicket = $ticket['type'] === 'client';
@endphp

<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        Updates
        @if ($comments !== [])
            <span class="tab-count">{{ count($comments) }}</span>
        @endif
    </div>

    @if ($comments === [])
        <p class="thread-empty">Nothing has been added to this ticket yet.</p>
    @else
        {{--
            An ordered list: the sequence is the meaning.

            Every item states in words whether the client can read it. The
            colour and the rail are there to catch the eye; the label is there
            because colour alone fails for anyone who cannot distinguish it,
            and this is not a distinction to get wrong.

            The controller passed AUDIENCE_STAFF, which is why internal notes
            are here at all. The client realm passes AUDIENCE_CLIENT and never
            receives them.
        --}}
        <ol class="thread">
            @foreach ($comments as $comment)
                @php $internal = P::isInternal($comment); @endphp
                <li class="thread-item @if ($internal) is-internal @endif">
                    <span class="avatar thread-avatar {{ Avatar::tint($comment['author']) }}" aria-hidden="true">{{ Avatar::initials($comment['author']) }}</span>

                    <div class="thread-body">
                        <div class="thread-head">
                            <span class="thread-author">{{ $comment['author'] }}</span>
                            <span class="thread-role">{{ $comment['role'] }}</span>

                            @if ($internal)
                                <span class="thread-tag is-internal">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    </svg>
                                    Internal only
                                </span>
                            @elseif ($isClientTicket)
                                {{-- Only worth saying on a client ticket: on an
                                     internal one there is no client to read it. --}}
                                <span class="thread-tag is-public">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    Client can see
                                </span>
                            @endif

                            <span class="thread-time">{{ P::date($comment['at']) }}, {{ P::time($comment['at']) }}</span>
                        </div>

                        <p class="thread-text">{{ $comment['body'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif

    {{--
        Two buttons, not one button and a toggle.

        A toggle has a default, and a default is a thing to get wrong on the one
        occasion it matters. Two labelled verbs make the choice explicit every
        time, and the internal button does not look like a quieter version of
        the other — it carries its own colour so the pair never reads as
        primary and secondary.
    --}}
    <form class="composer" method="POST" action="{{ route('tickets.comment', ['ticket' => $ticket['id']]) }}">
        @csrf
        <span class="avatar tint-1" aria-hidden="true">—</span>

        <div class="composer-body">
            <label class="sr-only" for="ticket-comment">Add an update</label>
            <textarea id="ticket-comment" name="body" placeholder="Write an update…" disabled></textarea>

            <div class="composer-actions">
                @if ($isClientTicket)
                    <button class="btn btn-primary" type="submit" name="visibility" value="public" disabled title="Posting is not built yet">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                        Reply to client
                    </button>
                @endif

                <button class="btn btn-outline btn-internal" type="submit" name="visibility" value="internal" disabled title="Posting is not built yet">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Internal note
                </button>

                <p class="composer-hint">
                    @if ($isClientTicket)
                        A reply is emailed to {{ $ticket['client'] }} and shown in their portal.
                        An internal note is never shown to them.
                    @else
                        This is an internal ticket, so there is no client to see it.
                    @endif
                </p>
            </div>
        </div>
    </form>
</div>
