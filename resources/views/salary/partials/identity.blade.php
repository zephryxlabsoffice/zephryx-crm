@php use App\Support\SalaryPresenter as P; @endphp

<section class="rail-card">
    <div class="rail-hd">
        <strong>On record for you</strong>
    </div>

    @if ($identity === null)
        <p class="rail-empty">Nothing on record.</p>
    @else
        {{--
            The only place in this module where these fields appear, and only
            because this is the viewer's own record.

            Every value below was masked in PHP before it reached this template
            (App\Support\Sensitive). The full numbers are not in the HTML, not
            in a data attribute, and not in any payload this page received —
            masking done in the browser is a decoration over a leak, because
            whatever the browser was sent has already been read by whoever is
            sitting at it.
        --}}
        <div>
            <div class="stat-row">
                <span class="stat-label">Bank</span>
                <span class="stat-value">{{ $identity['bank'] }}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Account</span>
                <span class="stat-value stat-value-mono">{{ $identity['account'] }}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">IFSC</span>
                {{-- Unmasked on purpose: it identifies a branch, not a person,
                     and is printed on every cheque. --}}
                <span class="stat-value stat-value-mono">{{ $identity['ifsc'] }}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">PAN</span>
                <span class="stat-value stat-value-mono">{{ $identity['pan'] }}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Aadhaar</span>
                <span class="stat-value stat-value-mono">{{ $identity['aadhaar'] }}</span>
            </div>
            <div class="stat-row">
                <span class="stat-label">Paid by</span>
                <span class="stat-value">{{ $latest['method'] }}</span>
            </div>
        </div>

        <p class="sl-privacy">
            Shown only to you. Nobody else sees these on any screen — correcting
            them is a request to HR, so a change to where your pay lands is
            never a thing that happens quietly.
        </p>
    @endif
</section>
