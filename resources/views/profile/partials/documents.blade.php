@php use App\Support\ProfilePresenter as P; @endphp

{{--
    ─────────────────────────────────────────────────────────────────────────────
    THE MOST SENSITIVE CARD IN THE APPLICATION

    A PAN or Aadhaar scan is not the same class of data as a masked number.
    App\Support\Sensitive keeps the NUMBERS off screens; these are the cards
    themselves — photograph, address, full number — in files that can be
    downloaded once and then exist somewhere nobody is tracking.

    WHAT THE BACKEND OWES, AND NONE OF IT IS OPTIONAL (§6):

    1. STORED OUTSIDE THE WEBROOT, under a name nobody can guess. A scan of
       somebody's Aadhaar at a predictable path under /storage is a link that
       works for anyone who tries it, forever, with no session involved.

    2. DOWNLOADED THROUGH A ROUTE, NOT LINKED AS A FILE. The route checks who is
       asking, issues a short-lived signed URL, and writes an audit entry naming
       who downloaded whose document and when. `profile.documents.download`
       exists for that; it must never become a redirect to a static path.

    3. REACHABLE BY TWO PARTIES ONLY — the person themselves and whoever holds
       the HR permission. Not managers, not the person's team, not anybody who
       happens to be an admin of something else.

    4. ENCRYPTED AT REST, and excluded from any backup that leaves the building
       unencrypted. A cPanel backup is a file somebody can email.

    5. NO PREVIEWS, NO THUMBNAILS. A thumbnail is the document, smaller. This
       list shows a name, a size and a date, which is enough to know what is
       held without rendering any of it.
    ─────────────────────────────────────────────────────────────────────────────
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>Your documents</strong>
    </div>

    @if ($documents->isEmpty())
        <p class="rail-empty">Nothing on file.</p>
    @else
        <div class="rail-list">
            @foreach ($documents as $document)
                <a class="rail-row" href="{{ route('profile.documents.download', ['document' => $document['id']]) }}">
                    <span class="rail-ic {{ $document['kind'] === 'identity' ? 'tone-danger' : 'tone-accent' }}" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                        </svg>
                    </span>
                    <span class="rail-body">
                        <strong>{{ $document['name'] }}</strong>
                        <span>
                            {{ P::fileSize($document['bytes']) }} ·
                            {{ $document['uploaded_by'] === 'hr' ? 'added by HR' : 'added by you' }}
                        </span>
                    </span>
                    <span class="rail-time">{{ P::date($document['uploaded_at']) }}</span>
                </a>
            @endforeach
        </div>
    @endif

    <p class="pf-rail-note">
        {{-- Says who can reach them. Somebody about to upload a scan of their
             Aadhaar deserves to know that before they do it, not after. --}}
        Only you and HR can open these, and every download is logged. Identity
        documents are never shown to anyone else on any screen.
    </p>

    <form method="POST" action="{{ route('profile.documents.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-field">
            <label class="form-field-lbl sr-only" for="pf-doc">Add a document</label>
            <input id="pf-doc" name="document" type="file" accept="application/pdf,image/*" disabled>
        </div>
        <button class="btn btn-outline btn-sm pf-rail-btn" type="submit" disabled title="Uploading is not built yet">
            Add a document
        </button>
    </form>
</section>
