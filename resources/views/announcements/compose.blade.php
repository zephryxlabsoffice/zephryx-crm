@extends('layouts.app')

@section('title', 'Post an announcement')

@section('content')
    @php
        $defaultForm = (array) config('announcements.default_form');
        $isScheduling = old('starts_on') !== null && old('starts_on') > now()->toDateString();
    @endphp

    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('announcements.manage') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Manage
            </a>
            <h1>Post an announcement</h1>
            <p>Choose what it is about first. The form then asks only what that kind needs.</p>
        </div>
    </div>

    <form class="an-form" method="POST" action="{{ route('announcements.store') }}"
          data-an-form
          data-an-forms="{{ json_encode($forms) }}"
          data-an-can-close="{{ $canDeclareHoliday ? '1' : '0' }}"
          data-an-today="{{ now()->toDateString() }}">
        @csrf

        <section class="an-form-grid">
            <div class="an-form-main">
                <div class="card">
                    <div class="section-hd"><span class="an-step-no">1</span> What is it about?</div>

                    <fieldset class="an-kinds">
                        <legend class="sr-only">Kind of announcement</legend>
                        {{-- Milestones are absent by construction: they are computed from
                             employee records. See AnnouncementPresenter::authorableCategories(). --}}
                        @foreach ($categories as $key => $meta)
                            <label class="an-kind">
                                <input type="radio" name="category" value="{{ $key }}" required @checked(old('category') === (string) $key)>
                                <span class="an-kind-box">
                                    <span class="an-card-ic {{ $meta['tone'] }}" aria-hidden="true">
                                        @include('partials.nav-icon', ['icon' => $meta['icon']])
                                    </span>
                                    <span class="an-kind-text">
                                        <strong>{{ $meta['label'] }}</strong>
                                        <span>{{ $forms[$key]['hint'] }}</span>
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </fieldset>
                    @error('category') <span class="field-error an-kinds-error">{{ $message }}</span> @enderror
                    <p class="pay-hint an-kinds-hint" data-an="kind-hint">Choose one to continue.</p>
                </div>

                <div class="an-steps" data-an="steps">
                    <div class="card">
                        <div class="section-hd"><span class="an-step-no">2</span> The details</div>

                        <div class="form-grid">
                            <div class="form-field an-form-wide">
                                <label class="form-field-lbl" for="an-title" data-an="title-label">{{ $defaultForm['title_label'] }}</label>
                                <input id="an-title" data-an="title" name="title" type="text" required maxlength="200" placeholder="{{ $defaultForm['title_placeholder'] }}" value="{{ old('title') }}">
                                @error('title') <span class="field-error">{{ $message }}</span> @enderror
                            </div>

                            <div class="an-form-wide an-subgrid" data-an="holiday">
                                @if ($canDeclareHoliday)
                                    {{-- These dates are read by App\Support\Holidays: on them,
                                         nobody is marked absent. A draft closes nothing. --}}
                                    <fieldset class="form-field an-form-wide an-choice">
                                        <legend class="form-field-lbl">Is the office closed?</legend>
                                        <label><input type="radio" name="closes_office" value="1" @checked(old('closes_office', '1') === '1')> Yes, the office is closed</label>
                                        <label><input type="radio" name="closes_office" value="0" @checked(old('closes_office', '1') === '0')> No, this is only a reminder</label>
                                    </fieldset>

                                    <div class="an-form-wide an-subgrid" data-an="closure">
                                        <div class="form-field">
                                            <label class="form-field-lbl" for="an-closed-from">First day closed</label>
                                            <input id="an-closed-from" data-an="closed-from" name="observed_from" type="date" value="{{ old('observed_from') }}">
                                            @error('observed_from') <span class="field-error">{{ $message }}</span> @enderror
                                        </div>

                                        <div class="form-field">
                                            <label class="form-field-lbl" for="an-closed-to">Last day closed <span class="an-optional">(same day if blank)</span></label>
                                            <input id="an-closed-to" data-an="closed-to" name="observed_to" type="date" value="{{ old('observed_to') }}">
                                            @error('observed_to') <span class="field-error">{{ $message }}</span> @enderror
                                        </div>

                                        <p class="an-closure-warn an-form-wide">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
                                                <path d="M12 9v4M12 17h.01"/>
                                            </svg>
                                            <span>
                                                <strong>Nobody is marked absent on these days.</strong>
                                                They stop counting as working days for everyone in the company.
                                                Check the weekday in the summary before you post.
                                            </span>
                                        </p>
                                    </div>
                                @else
                                    <p class="pay-hint an-form-wide">
                                        This posts a notice only. Closing the office, which changes attendance, is done by HR or the CEO.
                                    </p>
                                @endif
                            </div>

                            <div class="an-form-wide an-subgrid" data-an="event">
                                <div class="form-field" data-an="event-date-field">
                                    <label class="form-field-lbl" for="an-event-date" data-an="event-date-label">Date</label>
                                    <input id="an-event-date" data-an="event-date" name="event_date" type="date" value="{{ old('event_date') }}">
                                    @error('event_date') <span class="field-error">{{ $message }}</span> @enderror
                                </div>

                                <div class="form-field" data-an="event-time-field">
                                    <label class="form-field-lbl" for="an-event-time">Time <span class="an-optional">(optional)</span></label>
                                    <input id="an-event-time" name="event_time" type="time" value="{{ old('event_time') }}">
                                    @error('event_time') <span class="field-error">{{ $message }}</span> @enderror
                                </div>

                                <div class="form-field an-form-wide" data-an="event-place-field">
                                    <label class="form-field-lbl" for="an-event-place" data-an="event-place-label">Place</label>
                                    <input id="an-event-place" name="event_place" type="text" maxlength="200" value="{{ old('event_place') }}">
                                    @error('event_place') <span class="field-error">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="form-field an-form-wide">
                                <label class="form-field-lbl" for="an-body" data-an="body-label">{{ $defaultForm['body_label'] }}</label>
                                <textarea id="an-body" data-an="body" name="body" rows="5" maxlength="5000" placeholder="{{ $defaultForm['body_placeholder'] }}">{{ old('body') }}</textarea>
                                @error('body') <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="section-hd"><span class="an-step-no">3</span> Who sees it?</div>

                        <div class="form-grid">
                            <div class="form-field">
                                <label class="form-field-lbl" for="an-audience">Staff</label>
                                <select id="an-audience" data-an="audience" name="audience" required>
                                    @foreach ($audiences as $key => $label)
                                        <option value="{{ $key }}" @selected(old('audience') === (string) $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('audience') <span class="field-error">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-field" data-an="department-field">
                                <label class="form-field-lbl" for="an-department">Which department</label>
                                <select id="an-department" data-an="department" name="audience_department_id">
                                    <option value="">Choose</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}"
                                            @selected((int) old('audience_department_id') === $department->id)>
                                            {{ $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('audience_department_id')
                                    <span class="field-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-field an-form-wide">
                                <label class="form-field-lbl" for="an-for-clients">
                                    <input id="an-for-clients" data-an="for-clients" name="for_clients" type="checkbox" value="1" @checked(old('for_clients'))>
                                    Also show it to clients on their portal
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="section-hd"><span class="an-step-no">4</span> When does it go on the board?</div>

                        <div class="form-grid">
                            <fieldset class="form-field an-form-wide an-choice">
                                <legend class="sr-only">When it goes on the board</legend>
                                <label><input type="radio" name="an_when" value="now" @checked(! $isScheduling)> Straight away</label>
                                <label><input type="radio" name="an_when" value="later" @checked($isScheduling)> On a later date</label>
                            </fieldset>

                            <div class="form-field" data-an="starts-on-field">
                                <label class="form-field-lbl" for="an-from">Show from</label>
                                <input id="an-from" data-an="starts-on" name="starts_on" type="date" required value="{{ old('starts_on', now()->toDateString()) }}">
                                @error('starts_on') <span class="field-error">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-field" data-an="ends-on-field">
                                <label class="form-field-lbl" for="an-to">Take off the board on <span class="an-optional">(optional)</span></label>
                                <input id="an-to" data-an="ends-on" name="ends_on" type="date" value="{{ old('ends_on') }}">
                                @error('ends_on') <span class="field-error">{{ $message }}</span> @enderror
                                <span class="pay-hint">Leave blank to keep it until someone takes it down.</span>
                            </div>

                            <p class="pay-hint an-form-wide" data-an="ends-auto" hidden>It comes off the board by itself after that date.</p>

                            <div class="form-field an-form-wide">
                                <label class="form-field-lbl" for="an-pinned">
                                    <input id="an-pinned" name="pinned" type="checkbox" value="1" @checked(old('pinned'))>
                                    Keep it at the top of the board
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="rail">
                <section class="rail-card">
                    <div class="rail-hd"><strong>What will happen</strong></div>

                    <p class="rail-empty" data-an="summary-empty">Choose what it is about, and the result shows here.</p>
                    <ul class="an-summary" data-an="summary" hidden></ul>

                    <div class="an-submit">
                        <button class="btn btn-primary" type="submit" name="publish" value="1" data-an="post-button">
                            Post now
                        </button>
                        {{-- Save and post are two acts. An announcement sent by
                             accident cannot be unsent. --}}
                        <button class="btn btn-outline" type="submit" name="publish" value="0">
                            Save as draft
                        </button>
                    </div>
                </section>
            </aside>
        </section>
    </form>
@endsection
