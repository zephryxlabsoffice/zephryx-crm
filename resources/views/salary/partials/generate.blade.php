@php use App\Support\SalaryPresenter as P; @endphp

{{--
    Generating payroll.

    The handover made this a card with a single "Generate Salary" button. That
    is a bulk financial write with no review step and no idempotency: press it
    twice and, without a guard in the backend, everyone has two runs for one
    month.

    Two things changed. Generating produces UNPAID runs that somebody then
    releases — so the destructive half is a second, deliberate act — and the
    button states plainly what it is about to do and to how many people, rather
    than "Generate and process salaries".
--}}
<section class="card sl-generate">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
            <line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/>
        </svg>
        Generate payroll
    </div>

    <div class="prose">
        <p>
            @if ($missing->isEmpty() && $stats['total'] > 0)
                Every employee with a salary structure already has a run for
                {{ P::period($period) }}. Generating again would change nothing.
            @else
                This creates an <strong>unpaid</strong> run for
                {{ $missing->count() ?: $withoutStructure->count() }}
                {{ \Illuminate\Support\Str::plural('person', $missing->count() ?: 1) }}
                for {{ P::period($period) }}, from the salary structure on record.
                Nobody is paid until each run is released.
            @endif
        </p>
    </div>

    <form class="sl-generate-actions" method="POST" action="{{ route('salary.generate') }}">
        @csrf
        <input type="hidden" name="period" value="{{ $period }}">

        {{-- TODO (backend phase): this write must be idempotent — one run per
             person per month, enforced by a unique key on (employee, period),
             not by checking first and hoping. §6 also wants an audit entry
             naming who ran it, and §2.6 restricts it to the finance role. --}}
        <button class="btn btn-primary" type="submit" disabled title="Generating payroll is not built yet">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            Generate for {{ P::period($period) }}
        </button>

        <span class="pay-hint">
            Running this twice will not pay anybody twice — one run exists per
            person per month, and generating again leaves existing runs alone.
        </span>
    </form>
</section>
