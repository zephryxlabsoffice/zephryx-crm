@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <div class="page-hd">
        <h1>Settings</h1>
        <p>Company configuration. Every change is recorded against your account.</p>
    </div>

    {{--
        ─────────────────────────────────────────────────────────────────────────
        ONE SETTING, ONE REVIEW, ONE AUDIT ENTRY

        Each row is its own form. That is not an accident of layout: several of
        these values re-judge records that already exist, so each change has to
        be reviewed on its own terms — a single "Save all" button would batch a
        harmless brand-name edit together with a change that reclassifies two
        months of attendance, and present one confirmation for both.

        One setting at a time also means one audit entry per change, with a
        before and an after that mean something (§6).
        ─────────────────────────────────────────────────────────────────────────
    --}}

    @foreach ($groups as $key => $group)
        <section class="card ad-group">
            <div class="card-hd">
                <span class="card-title">{{ $group['label'] }}</span>

                @if ($group['retroactive'])
                    <span class="pill pill-amber">Affects existing records</span>
                @endif
            </div>

            <div class="card-body">
                <p class="ad-group-note">{{ $group['note'] }}</p>

                @if ($group['retroactive'])
                    {{--
                        Said once per group, in the place somebody reads before
                        they touch a field rather than after.

                        This is the finding the whole module is shaped around:
                        Attendance and Leave derive their judgements on read, so
                        these numbers are applied to the past as well as the
                        future. See App\Support\Admin\SettingsCatalogue.
                    --}}
                    @include('partials.notice', [
                        'tone' => 'warning',
                        'title' => 'Changing these re-judges records that already exist',
                        'message' => 'Attendance and leave are worked out from these values every time a page '
                            .'is opened, not stored when the day happens. A change here applies backwards as '
                            .'well as forwards. You will be shown exactly what moves before anything is saved.',
                    ])
                @endif

                <div class="ad-settings">
                    @foreach ($group['settings'] as $setting)
                        <form class="ad-setting" method="POST" action="{{ route('admin.settings.preview') }}">
                            @csrf
                            <input type="hidden" name="key" value="{{ $setting['key'] }}">

                            <div class="ad-setting-label">
                                <label class="form-field-lbl" for="set-{{ $loop->parent->index }}-{{ $loop->index }}">
                                    {{ $setting['label'] }}
                                </label>
                                <span class="ad-setting-key">{{ $setting['key'] }}</span>
                            </div>

                            <div class="ad-setting-field">
                                @include('admin.settings.field', [
                                    'setting' => $setting,
                                    'id' => 'set-'.$loop->parent->index.'-'.$loop->index,
                                ])

                                <p class="ad-setting-note">
                                    {{ $setting['note'] }}
                                    @if ($setting['retroactive'])
                                        <strong>{{ $setting['affects'] }}</strong>
                                    @endif
                                </p>
                            </div>

                            <div class="ad-setting-action">
                                {{-- "Review", not "Save". The button says what
                                     it does: the next screen names what would
                                     change, and only that screen writes. --}}
                                <button class="btn btn-outline" type="submit">Review</button>
                            </div>
                        </form>
                    @endforeach
                </div>
            </div>
        </section>
    @endforeach
@endsection
