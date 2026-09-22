@php use App\Support\ProfilePresenter as P; @endphp

{{--
    HR's read of the same documents the person's own My Profile page shows
    (review round Q16, answered 2026-09-21: "the employee record page shows
    documents to employees.identifiers holders … without this, HR can log
    that a photocopy arrived but never actually check it against the
    record"). Same file, same route shape, same two-party rule as
    resources/views/profile/partials/documents.blade.php — this is the
    second party, not a new capability.

    Read-only on purpose: nothing here uploads on the employee's behalf.
    Adding a document to somebody else's record is a decision this round
    did not ask for, and My Profile already owns the write.
--}}
<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
        </svg>
        Documents
    </div>

    @if ($documents->isEmpty())
        <p class="rail-empty">Nothing on file.</p>
    @else
        <div class="rail-list">
            @foreach ($documents as $document)
                <a class="rail-row" href="{{ route('employees.documents.view', ['employee' => $employee['user_id'], 'document' => $document['id']]) }}" target="_blank" rel="noopener">
                    <span class="rail-ic {{ $document['kind'] === 'identity' ? 'tone-danger' : 'tone-accent' }}" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                        </svg>
                    </span>
                    <span class="rail-body">
                        <strong>{{ $document['name'] }}</strong>
                        <span>
                            {{ P::fileSize($document['bytes']) }} ·
                            {{ $document['uploaded_by'] === 'hr' ? 'added by HR' : 'added by them' }}
                        </span>
                    </span>
                    <span class="rail-time">{{ P::date($document['uploaded_at']) }}</span>
                </a>
            @endforeach
        </div>
    @endif

    <p class="dash-note">
        Every view and download here is logged against this record, the same as
        a revealed identifier.
    </p>
</div>
