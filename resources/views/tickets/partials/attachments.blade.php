@php use App\Support\TaskPresenter as F; @endphp

<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
        </svg>
        Attachments
        @if ($attachments !== [])
            <span class="tab-count">{{ count($attachments) }}</span>
        @endif
    </div>

    <div class="card-body">
        @if ($attachments === [])
            <p class="rail-empty">No files on this ticket.</p>
        @else
            {{--
                Files on a client ticket are uploaded by someone outside the
                company. §6: validated by type and size, stored outside the web
                root, and served through a controller that checks the viewer may
                have them — never linked straight at a public path, and never
                rendered inline.
            --}}
            <div class="attachment-grid">
                @foreach ($attachments as $file)
                    @php $type = F::fileType($file['name']); @endphp
                    <span class="attachment">
                        <a class="attachment-open" href="{{ route('tickets.attachments.view', ['ticket' => $ticket['id'], 'attachment' => $file['id']]) }}" target="_blank" rel="noopener noreferrer">
                            <span class="attachment-ic {{ $type['class'] }}" aria-hidden="true">{{ $type['label'] }}</span>
                            <span class="attachment-body">
                                <strong>{{ $file['name'] }}</strong>
                                <span>{{ $file['kind'] }} · {{ $file['size'] }}</span>
                            </span>
                        </a>
                        <a href="{{ route('tickets.attachments.download', ['ticket' => $ticket['id'], 'attachment' => $file['id']]) }}"
                           aria-label="Download {{ $file['name'] }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                        </a>
                    </span>
                @endforeach
            </div>
        @endif

        <form class="pay-form" method="POST" action="{{ route('tickets.attachments.store', ['ticket' => $ticket['id']]) }}" enctype="multipart/form-data">
            @csrf

            <div class="pay-field">
                <label class="form-field-lbl sr-only" for="tkt-attachment">Add a file</label>
                <input id="tkt-attachment" name="document" type="file" accept="application/pdf,image/png,image/jpeg" required>
                @error('document')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>

            <button class="btn btn-outline" type="submit">Attach file</button>
        </form>
        <p class="pay-hint">PDF, PNG or JPEG, up to 8 MB.</p>
    </div>
</div>
