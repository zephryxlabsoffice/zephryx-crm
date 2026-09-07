@extends('layouts.app')

@section('title', 'Review change')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('admin.settings') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Settings
            </a>
            <h1>{{ $setting['label'] }}</h1>
            <p>{{ $setting['key'] }}</p>
        </div>
    </div>

    {{--
        ─────────────────────────────────────────────────────────────────────────
        THE SCREEN THIS MODULE EXISTS FOR

        The value is the small half of this page. The large half is what the
        value DOES to records that already exist — computed against the real
        history by App\Support\Admin\Retroactive, not estimated.

        "half_day_hours: 4 → 6" is a true sentence that tells nobody anything.
        "47 days across 11 people would be re-judged" is the sentence somebody
        needs before they press the button, and afterwards it is the sentence
        the audit log keeps.
        ─────────────────────────────────────────────────────────────────────────
    --}}

    @if ($unchanged)
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'That is the value it already has',
            'message' => 'Nothing would change. Go back and edit the field, or leave it as it is.',
        ])
    @endif

    <section class="dash-grid">
        <div class="dash-main">
            <section class="card">
                <div class="card-hd">
                    <span class="card-title">The change</span>
                </div>

                <div class="card-body">
                    <div class="ad-change">
                        <div class="ad-change-side">
                            <span class="ad-change-lbl">Now</span>
                            {{-- Written out rather than printed raw: "0, 6" is
                                 a correct and unreadable way to describe the
                                 weekly off on the one screen where somebody has
                                 to be sure what they are agreeing to. --}}
                            <strong>@include('admin.settings.value', ['setting' => $setting, 'value' => $setting['value']])</strong>
                        </div>

                        <svg class="ad-change-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                        </svg>

                        <div class="ad-change-side is-proposed">
                            <span class="ad-change-lbl">After</span>
                            <strong>@include('admin.settings.value', ['setting' => $setting, 'value' => $proposed])</strong>
                        </div>
                    </div>
                </div>
            </section>

            @if ($effect !== null)
                <section class="card">
                    <div class="card-hd">
                        <span class="card-title">What this does to existing records</span>
                        @if ($effect['affected'] > 0)
                            <span class="pill pill-amber">{{ $effect['affected'] }} affected</span>
                        @endif
                    </div>

                    <div class="card-body">
                        <p class="ad-effect-summary">{{ $effect['summary'] }}</p>

                        @if ($effect['changes'] !== [])
                            <div class="card-body-table ad-effect-table">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Who</th>
                                            <th scope="col">When</th>
                                            <th scope="col">Now</th>
                                            <th scope="col">After</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($effect['changes'] as $change)
                                            <tr>
                                                <td><strong>{{ $change['who'] }}</strong></td>
                                                <td class="cell-tight">{{ $change['when'] }}</td>
                                                <td class="cell-tight">{{ $change['from'] }}</td>
                                                <td class="cell-tight"><span class="is-soon">{{ $change['to'] }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if ($effect['affected'] > count($effect['changes']))
                                {{-- The scale, not four hundred rows nobody
                                     reads. The examples are there to make the
                                     number concrete. --}}
                                <p class="ad-effect-more">
                                    Showing {{ count($effect['changes']) }} of {{ $effect['affected'] }}.
                                </p>
                            @endif
                        @endif

                        <p class="ad-effect-warning">
                            These records are not edited — they are re-judged. Nothing in them
                            shows that the rule moved, which is why this change is recorded
                            against your account with the figures above.
                        </p>
                    </div>
                </section>
            @endif
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Save this change</strong>
                </div>

                @if ($setting['retroactive'])
                    <p class="dash-note dash-note-warn">
                        This setting applies to the past as well as the future.
                    </p>
                @endif

                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="key" value="{{ $setting['key'] }}">

                    @if (is_array($proposed))
                        @foreach ($proposed as $item)
                            <input type="hidden" name="value[]" value="{{ $item }}">
                        @endforeach
                    @else
                        <input type="hidden" name="value" value="{{ $proposed }}">
                    @endif

                    {{-- TODO (backend phase): re-compute the preview here and
                         refuse if it has moved. The figures above are a
                         snapshot, and between the two steps somebody may have
                         checked in — confirming against a stale preview means
                         agreeing to a number nobody ever saw. --}}
                    <div class="dash-punch-action">
                        <button class="btn btn-primary" type="submit" disabled
                                title="Saving is not built yet" @disabled($unchanged)>
                            Save change
                        </button>
                        <a class="dash-link" href="{{ route('admin.settings') }}">Cancel</a>
                    </div>
                </form>
            </section>

            <section class="rail-card">
                <div class="rail-hd">
                    <strong>What gets recorded</strong>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Setting</span>
                    <span class="stat-value">{{ $setting['key'] }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Before</span>
                    <span class="stat-value">@include('admin.settings.value', ['setting' => $setting, 'value' => $setting['value']])</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">After</span>
                    <span class="stat-value">@include('admin.settings.value', ['setting' => $setting, 'value' => $proposed])</span>
                </div>

                @if ($effect !== null && $effect['affected'] > 0)
                    <div class="stat-row">
                        <span class="stat-label">Effect</span>
                        <span class="stat-value"><span class="is-soon">{{ $effect['summary'] }}</span></span>
                    </div>
                @endif
            </section>
        </aside>
    </section>
@endsection
