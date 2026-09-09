@php
    use App\Models\ProjectUpdate;
    use App\Support\Avatar;
@endphp

{{--
    The end-of-day log, INTERNAL NOTES INCLUDED. This is the staff side, and
    internal notes are what it is for — the client portal reads
    ProjectUpdate::clientVisible and has no way to reach this list at all.

    Every row states which of the two it is, in words, because somebody
    scanning the log needs to know at a glance whether what they are reading has
    been seen by the client.
--}}
<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>
        </svg>
        End of day updates
    </div>

    @forelse ($updates as $update)
        <article class="upd-row">
            <div class="att-record-hd">
                <div class="person-row">
                    <span class="avatar {{ Avatar::tint((string) $update->author?->user?->name) }}" aria-hidden="true">
                        {{ Avatar::initials((string) $update->author?->user?->name) }}
                    </span>
                    <span class="person-body">
                        <strong>{{ $update->title }}</strong>
                        <span>
                            {{ $update->author?->user?->name ?? 'Unknown' }}
                            · {{ $update->created_at?->format('d M Y, g:i A') }}
                        </span>
                    </span>
                </div>

                @if ($update->visibility === ProjectUpdate::CLIENT)
                    <span class="pill pill-green">Shared with the client</span>
                @else
                    <span class="pill pill-gray">Internal only</span>
                @endif
            </div>

            <div class="prose">
                <p>{{ $update->body }}</p>
            </div>

            @if ($mayPublish)
                <form method="POST" action="{{ route('projects.updates.visibility', ['project' => $project['id'], 'update' => $update->reference]) }}">
                    @csrf
                    <input type="hidden" name="visibility"
                           value="{{ $update->visibility === ProjectUpdate::CLIENT ? ProjectUpdate::INTERNAL : ProjectUpdate::CLIENT }}">
                    <button class="chip-btn" type="submit">
                        {{ $update->visibility === ProjectUpdate::CLIENT ? 'Hide from the client' : 'Share with the client' }}
                    </button>
                </form>

                @if ($update->visibility !== ProjectUpdate::CLIENT && $update->wasPublished())
                    {{-- Hiding is housekeeping, not a recall. If the client has
                         read it, they have read it, and the page must not
                         suggest otherwise. --}}
                    <p class="att-rail-note">
                        This was shared with the client on {{ $update->published_at->format('d M Y') }}
                        and may already have been read.
                    </p>
                @endif
            @endif
        </article>
    @empty
        <div class="table-empty">
            <strong>No updates yet.</strong>
            Whoever is on this project can post one from Submit EOD.
        </div>
    @endforelse
</div>
