@extends('layouts.app')

@section('title', 'Post an announcement')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('announcements.manage') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Manage
            </a>
            <h1>Post an announcement</h1>
            <p>Everyone with access reads this, so it is worth a second read first.</p>
        </div>
    </div>

    @include('partials.notice', [
        'tone' => 'info',
        'title' => 'This form does not save yet',
        'message' => 'The fields and the validation shape are real; the write lands with the backend. Nothing typed here is stored and nobody is notified.',
    ])

    <form class="an-form" method="POST" action="{{ route('announcements.store') }}">
        @csrf

        <section class="an-form-grid">
            <div class="an-form-main">
                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 11l18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>
                        </svg>
                        What you want to say
                    </div>

                    <div class="form-grid">
                        <div class="form-field an-form-wide">
                            <label class="form-field-lbl" for="an-title">Title</label>
                            <input id="an-title" name="title" type="text" placeholder="Office closed on Friday" disabled>
                            <span class="pay-hint">This is what people see on the board and in the list.</span>
                        </div>

                        <div class="form-field an-form-wide">
                            <label class="form-field-lbl" for="an-body">The announcement</label>
                            <textarea id="an-body" name="body" rows="6" placeholder="What is happening, when, and what anyone needs to do about it." disabled></textarea>
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="an-cat">Category</label>
                            {{-- Milestones are absent by construction: they are
                                 computed from employee records, and a
                                 hand-written one would look identical in the
                                 feed and be wrong next year. See
                                 AnnouncementPresenter::authorableCategories(). --}}
                            <select id="an-cat" name="category" disabled>
                                @foreach ($categories as $key => $meta)
                                    <option value="{{ $key }}">{{ $meta['label'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="an-audience">Who sees it</label>
                            <select id="an-audience" name="audience" disabled>
                                @foreach ($audiences as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="pay-hint">Anything narrower than everyone is enforced when it is fetched, not hidden in the page.</span>
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="an-department">Which department <span class="an-optional">(if not everyone)</span></label>
                            <select id="an-department" name="audience_value" disabled>
                                <option value="">—</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department }}">{{ $department }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        How long it runs
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="an-from">Goes up</label>
                            <input id="an-from" name="published_at" type="date" value="{{ now()->toDateString() }}" disabled>
                            <span class="pay-hint">A future date schedules it.</span>
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="an-to">Comes down <span class="an-optional">(optional)</span></label>
                            <input id="an-to" name="expires_at" type="date" disabled>
                            {{-- An end date is a kindness to everyone who reads
                                 the board later: an announcement about a closure
                                 last March is noise once it has happened. --}}
                            <span class="pay-hint">Leave blank for something that does not go stale, like a policy.</span>
                        </div>

                        <div class="form-field an-form-wide">
                            <label class="form-field-lbl" for="an-pinned">
                                <input id="an-pinned" name="pinned" type="checkbox" value="1" disabled>
                                Pin to the top of the board
                            </label>
                            <span class="pay-hint">Use it sparingly — everything pinned is nothing pinned.</span>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="rail">
                <section class="rail-card">
                    <div class="rail-hd"><strong>Before you post</strong></div>

                    <ul class="an-facts">
                        <li class="an-fact">
                            <span class="an-fact-ic tone-warn" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
                                    <path d="M12 9v4M12 17h.01"/>
                                </svg>
                            </span>
                            <span class="an-fact-body">
                                <strong>The board is only worth having while it is read</strong>
                                <span>If something concerns one person, it belongs in their notifications, not here.</span>
                            </span>
                        </li>

                        <li class="an-fact">
                            <span class="an-fact-ic tone-accent" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                                </svg>
                            </span>
                            <span class="an-fact-body">
                                <strong>Give it an end date if it will go stale</strong>
                                <span>A closure notice from last March is noise on a board somebody is scanning today.</span>
                            </span>
                        </li>
                    </ul>

                    <div class="an-submit">
                        <button class="btn btn-primary" type="submit" name="action" value="publish" disabled title="Posting is not built yet">
                            Post it
                        </button>
                        {{-- Save and post are two acts. An announcement sent by
                             accident cannot be unsent. --}}
                        <button class="btn btn-outline" type="submit" name="action" value="draft" disabled title="Saving is not built yet">
                            Save as draft
                        </button>
                    </div>
                </section>
            </aside>
        </section>
    </form>
@endsection
