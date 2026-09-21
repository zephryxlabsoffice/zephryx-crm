@php use App\Support\Avatar; @endphp

<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        Comments
        @if ($comments !== [])
            <span class="tab-count">{{ count($comments) }}</span>
        @endif
    </div>

    {{--
        No visibility split here, unlike a ticket thread: a task has one
        readership, staff, so there is nothing to choose between. See
        App\Models\TaskComment.
    --}}
    @if ($comments === [])
        <p class="thread-empty">Nothing has been added to this task yet.</p>
    @else
        <ol class="thread">
            @foreach ($comments as $comment)
                <li class="thread-item">
                    <span class="avatar thread-avatar {{ Avatar::tint($comment['author']) }}" aria-hidden="true">{{ Avatar::initials($comment['author']) }}</span>

                    <div class="thread-body">
                        <div class="thread-head">
                            <span class="thread-author">{{ $comment['author'] }}</span>
                            <span class="thread-time">{{ \App\Support\TaskPresenter::date($comment['at']->toDateString()) }}, {{ $comment['at']->format('g:i A') }}</span>
                        </div>

                        <p class="thread-text">{{ $comment['body'] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif

    <form class="composer" method="POST" action="{{ route('tasks.comment', ['task' => $task['id']]) }}">
        @csrf
        <span class="avatar tint-1" aria-hidden="true">—</span>

        <div class="composer-body">
            <label class="sr-only" for="task-comment">Add a comment</label>
            <textarea id="task-comment" name="body" placeholder="Write a comment…" required maxlength="5000">{{ old('body') }}</textarea>
            @error('body')
                <span class="field-error">{{ $message }}</span>
            @enderror

            <div class="composer-actions">
                <button class="btn btn-primary" type="submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                    Post comment
                </button>
            </div>
        </div>
    </form>
</div>
