@php
    use App\Support\Avatar;
    use App\Support\InvoicePresenter as P;
    $collected = $invoice['paid']->percentageOf($invoice['total']);
@endphp

<section class="rail-card">
    <div class="rail-hd">
        <strong>Status</strong>
        <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
    </div>

    <div class="inv-summary">
        {{--
            A native <progress>, not a styled div sized with `style="width:…"`.
            The handover's approach is blocked outright by our CSP (§6), and
            this announces itself to a screen reader without ARIA of our own.
        --}}
        <label class="inv-progress-lbl" for="inv-collected">Collected</label>
        <progress id="inv-collected" class="progress" max="100" value="{{ $collected }}"></progress>
        <span class="inv-progress-note">
            {{ $invoice['paid']->format() }} of {{ $invoice['total']->format() }}
            @if (! $invoice['balance']->isZero())
                <strong>{{ $invoice['balance']->format() }} outstanding</strong>
            @endif
        </span>
    </div>

    <div>
        <div class="stat-row">
            <span class="stat-label">Due</span>
            <span class="stat-value">
                {{ P::date($invoice['due_date']) }}
                @if ($due['tone'])
                    <span class="{{ $due['tone'] }}">{{ $due['label'] }}</span>
                @endif
            </span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Terms</span>
            <span class="stat-value">{{ P::terms($invoice) }}</span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Currency</span>
            <span class="stat-value">{{ $invoice['currency'] }}</span>
        </div>
        <div class="stat-row">
            <span class="stat-label">What it means</span>
            <span class="stat-value stat-value-quiet">{{ $pill['meaning'] }}</span>
        </div>
    </div>
</section>

<section class="rail-card">
    <div class="rail-hd">
        <strong>Client</strong>
    </div>

    <div class="rail-list">
        <div class="rail-row">
            <span class="avatar {{ Avatar::tint($invoice['client']) }}" aria-hidden="true">{{ Avatar::initials($invoice['client']) }}</span>
            <div class="rail-body">
                <strong>{{ $invoice['client'] }}</strong>
                <span>{{ $client['industry'] ?? 'Client' }}</span>
            </div>
        </div>

        @if ($invoice['project_record'])
            <div class="rail-row">
                <div class="rail-ic tone-accent" aria-hidden="true">
                    @include('partials.nav-icon', ['icon' => 'projects'])
                </div>
                <div class="rail-body">
                    <strong>
                        <a class="card-link" href="{{ route('projects.show', ['project' => $invoice['project_record']['id']]) }}">{{ $invoice['project_record']['name'] }}</a>
                    </strong>
                    <span>{{ $invoice['project_record']['id'] }}</span>
                </div>
            </div>
        @endif
    </div>
</section>
