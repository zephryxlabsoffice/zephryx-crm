@extends('layouts.standalone')

@php use App\Support\InvoicePresenter as P; @endphp

@section('title', $invoice['id'])

{{--
    ─────────────────────────────────────────────────────────────────────────────
    THE PRINTABLE INVOICE, AND IT DOES NOT CLAIM TO BE A PDF

    §6 asked for a generated PDF streamed through an authorising route. This host
    has no PDF library, and adding one is a deployment decision rather than a
    code change — the same wall the profile photo hit.

    So this is the document as a page built for printing, which every browser
    turns into a PDF with one keystroke. The button that leads here says
    "Printable copy" rather than "Download PDF", because a file that claims to be
    a PDF and is not is worse than an honest page.

    THE FIGURES COME FROM THE SAME PLACE AS THE SCREEN

    It renders `invoices.partials.document`, which is the partial the staff
    module built and the client's own invoice page uses. §9 makes this the one
    screen where our copy and their copy must be the same document, and this is
    the copy that gets printed and filed — the one that would be quoted back at
    us if it disagreed.

    The standalone layout is deliberate: no sidebar, no topbar, no navigation. A
    printed page carrying an application's chrome wastes the top third of a
    sheet, and the sidebar links mean nothing on paper.
    ─────────────────────────────────────────────────────────────────────────────
--}}

@section('content')
    <div class="inv-print">
        <div class="inv-print-note">
            {{-- Screen only: `.inv-print-note` is display:none in the print
                 stylesheet, so the sheet carries the invoice and not an
                 instruction about how to make one. --}}
            <p>
                This is the printable copy of {{ $invoice['id'] }}. Use your browser's print
                option to save it as a PDF or send it to a printer.
            </p>

            <a class="btn btn-outline" href="{{ route('client.invoices.show', $invoice['id']) }}">
                Back to the invoice
            </a>
        </div>

        @include('invoices.partials.document')

        @if ($invoice['payments'] !== [])
            @include('invoices.partials.payment-history')
        @endif
    </div>
@endsection
