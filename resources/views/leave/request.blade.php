@extends('layouts.app')

@php use App\Support\LeavePolicy; @endphp

@section('title', 'Request leave')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('leave.mine') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                My leave
            </a>
            <h1>Request leave</h1>
            <p>Nothing is deducted until it is approved.</p>
        </div>
    </div>

    @if (! $attends)
        {{-- A freelancer, paid against work rather than time (decided
             2026-09-11): there is no leave balance for them, so there is no
             form — not a disabled one, none at all. --}}
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'Nothing to request here',
            'message' => 'Freelance engagements carry no leave balance. This page does not apply to your record.',
        ])
    @else
    <form class="lv-form" method="POST" action="{{ route('leave.store') }}">
        @csrf

        <section class="lv-form-grid">
            <div class="lv-form-main">
                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        When, and what kind
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="leave-type-field">Leave type</label>
                            <select id="leave-type-field" name="type" required>
                                @foreach ($types as $key => $meta)
                                    <option value="{{ $key }}">
                                        {{ $meta['label'] }}@if ($meta['days'] !== null) — {{ collect($balance['types'])->firstWhere('key', $key)['remaining'] ?? $meta['days'] }} days left @endif
                                    </option>
                                @endforeach
                            </select>
                            <span class="pay-hint">Unpaid leave has no allowance and is agreed case by case.</span>
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="leave-days">How many days</label>
                            {{--
                                Typed, not calculated.

                                Decided 2026-08-28: this is a portal for
                                requesting and counting, not a rules engine. It
                                does not know which days are weekends or public
                                holidays, so it does not pretend to — the person
                                asking states the cost and the person approving
                                agrees it. Half days are allowed, hence 0.5
                                steps.
                            --}}
                            <input id="leave-days" name="days" type="number" min="0.5" step="0.5" required value="{{ old('days', 1) }}">
                            <span class="pay-hint">Half days are fine. Do not count weekends or holidays you would not have worked.</span>
                            {{-- The only arithmetic in the module: a figure the
                                 range could not contain at all is a typo, not a
                                 judgement. --}}
                            @error('days')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="leave-from">First day</label>
                            <input id="leave-from" name="from" type="date" required value="{{ old('from') }}">
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="leave-to">Last day</label>
                            <input id="leave-to" name="to" type="date" required value="{{ old('to') }}">
                            <span class="pay-hint">Same as the first day for a single day off.</span>
                            @error('to')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                            @error('from')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        Anything else
                    </div>

                    <div class="form-grid">
                        <div class="form-field lv-form-wide">
                            <label class="form-field-lbl" for="leave-reason">Reason</label>
                            <textarea id="leave-reason" name="reason" rows="3" required maxlength="1000" placeholder="A line is enough.">{{ old('reason') }}</textarea>
                            {{-- Says who reads it, because "fever, seeing a
                                 doctor" is health information and people should
                                 know where it goes before they type it. --}}
                            <span class="pay-hint">Read by whoever decides the request. It is not shown on any list.</span>
                            @error('reason')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="leave-contact">Contact while away <span class="lv-optional">(optional)</span></label>
                            <input id="leave-contact" name="contact" type="tel" placeholder="+91 …" value="{{ old('contact') }}">
                            <span class="pay-hint">Only if you are happy to be reached.</span>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="rail">
                @include('leave.partials.balance', ['heading' => 'Before you ask'])

                <section class="rail-card">
                    <div class="rail-hd"><strong>Then what</strong></div>
                    <div class="lv-submit">
                        <button class="btn btn-primary" type="submit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                            Send request
                        </button>
                        <p class="pay-hint pay-hint-block">
                            It goes to whoever approves leave. You can withdraw it
                            yourself any time before it starts.
                        </p>
                    </div>
                </section>
            </aside>
        </section>
    </form>
    @endif
@endsection
