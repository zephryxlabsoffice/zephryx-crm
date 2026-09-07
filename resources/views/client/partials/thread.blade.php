@php use App\Support\TicketPresenter as P; @endphp

{{--
    The ticket conversation, as the client sees it.

    ─────────────────────────────────────────────────────────────────────────────
    WHY THIS IS NOT tickets.partials.thread

    Reusing the staff partial here was the first attempt and it was wrong. That
    component is not just a list of comments: it bundles the staff composer and
    the staff visibility labels, and rendering it in the portal produced a page
    that showed the client

      - a button marked "Internal note", inviting them to post one;
      - the sentence "An internal note is never shown to them", about them;
      - a "Client can see" badge on every comment.

    None of that leaked a comment — the audience filter held, and the internal
    note was correctly absent. It leaked the MECHANISM, which is nearly as bad:
    it tells the client there is a private channel on their own ticket, on the
    page where they are least likely to take it well.

    The lesson is worth keeping: a component that is safe with the right data
    can still be wrong for the audience. Sharing the markup is fine; sharing the
    controls is not. This partial uses the same `.thread` styles and stops
    there.
    ─────────────────────────────────────────────────────────────────────────────

    Composing a reply lives on the page itself, as one button with one meaning.
    A client comment is client-visible by construction — they wrote it — so
    there is no visibility choice to offer and no default to get wrong.
--}}
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
        <ol class="thread">
            @foreach ($comments as $comment)
                <li class="thread-item">
                    <span class="avatar thread-avatar {{ \App\Support\Avatar::tint($comment['author']) }}" aria-hidden="true">
                        {{ \App\Support\Avatar::initials($comment['author']) }}
                    </span>

                    <div class="thread-body">
                        <div class="thread-head">
                            <span class="thread-author">{{ $comment['author'] }}</span>
                            <span class="thread-role">{{ $comment['role'] }}</span>

                            {{-- No visibility badge. Every comment that reaches
                                 this page is one the client may read, so a
                                 label saying so on each of them is noise that
                                 only raises the question of what the other kind
                                 would look like. --}}

                            <span class="thread-time">{{ P::date($comment['at']) }}, {{ P::time($comment['at']) }}</span>
                        </div>

                        <p class="thread-text">{{ $comment['body'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
