@php use App\Support\InvoicePresenter as P; @endphp

@if (($mayManage ?? false) && $invoice['status'] !== P::CANCELLED)
    {{--
        Attaching or replacing the document. Offered right up to cancellation
        — even a sent invoice may need its PDF corrected before payment
        arrives, and `send()` is what actually stops nothing reaching a
        client without one, not this form being hidden.
    --}}
    <section class="rail-card">
        <div class="rail-hd">
            <strong>{{ $invoice['has_document'] ? 'Replace document' : 'Attach document' }}</strong>
        </div>

        @if (! $invoice['has_document'])
            <p class="pay-hint pay-hint-block">
                Nothing is attached yet. This invoice cannot be sent until it is.
            </p>
        @endif

        <form class="pay-form" method="POST" action="{{ route('invoices.document.store', ['invoice' => $invoice['id']]) }}" enctype="multipart/form-data">
            @csrf

            <div class="pay-field">
                <label class="form-field-lbl sr-only" for="inv-doc">{{ $invoice['has_document'] ? 'Replacement document' : 'Document' }}</label>
                <input id="inv-doc" name="document" type="file" accept="application/pdf,image/png,image/jpeg" required>
                @error('document')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>

            <button class="btn btn-outline" type="submit">
                {{ $invoice['has_document'] ? 'Replace document' : 'Attach document' }}
            </button>
        </form>
    </section>
@endif
