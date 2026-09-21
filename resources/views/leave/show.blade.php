@extends('layouts.app')

@php
    use App\Support\Avatar;
    use App\Support\LeavePolicy;
    use App\Support\LeavePresenter as P;
    $pill = P::status($request['status']);
    $leaveType = LeavePolicy::type($request['type']);
    $timing = P::timing($request);
@endphp

@section('title', $request['id'])

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ $own ? route('leave.mine') : route('leave.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                {{ $own ? 'My leave' : 'Leave requests' }}
            </a>
            <h1>{{ P::range($request) }}</h1>
            <p>{{ $request['employee_record']['name'] }} · {{ $request['id'] }}</p>
        </div>

        @if ($canCancel)
            <div class="hd-actions">
                <form method="POST" action="{{ route('leave.cancel', ['leaveRequest' => $request['id']]) }}">
                    @csrf
                    {{-- The requester's own act. Pending, or approved and not
                         yet started — leave already under way is a conversation,
                         not a button. --}}
                    <button class="btn btn-outline" type="submit">
                        Withdraw request
                    </button>
                </form>
            </div>
        @endif
    </div>

    @if ($request['status'] === P::REJECTED && $request['note'])
        @include('partials.notice', [
            'tone' => 'danger',
            'title' => 'This request was rejected',
            'message' => $request['note'],
        ])
    @elseif ($request['status'] === P::CANCELLED)
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'Withdrawn by ' . ($own ? 'you' : $request['employee_record']['name']),
            'message' => $request['note'] ?: 'No longer needed. Nothing was deducted from the balance.',
        ])
    @elseif ($request['status'] === P::PENDING && $own)
        {{-- The one rule that cannot be delegated away. Shown on your OWN
             pending request — somebody who simply lacks the permission is not
             being told about a rule that has nothing to do with them. --}}
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'You cannot decide this request',
            'message' => 'Nobody approves their own leave, including the owner. Someone else holding the leave permission has to decide it.',
        ])
    @endif

    <section class="lv-detail-grid">
        <div class="lv-detail-main">
            @include('leave.partials.request-card')

            @if ($canDecide)
                @include('leave.partials.decide')
            @endif

            @include('leave.partials.clashes')

            @if ($own && $request['status'] === P::APPROVED)
                {{--
                    "Working a Sunday against leave already taken" — asked
                    BEFORE working it (decided 2026-09-11), not after. Manager
                    approval returns that one day to the balance and rosters
                    the date; see CompOffController::requestSundayAgainstLeave.
                --}}
                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Work a day instead
                    </div>

                    <div class="prose prose-quiet">
                        <p>
                            Want to work one of these days after all? Ask before you do —
                            once approved, that day returns to your balance. Has to be
                            asked for in the same month.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('leave.sundayAgainstLeave.request', ['leaveRequest' => $request['id']]) }}">
                        @csrf
                        <div class="form-field">
                            <label class="form-field-lbl" for="sal-date">Which day</label>
                            <input id="sal-date" name="date" type="date" required
                                   min="{{ $request['from'] }}" max="{{ $request['to'] }}">
                            @error('date')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <button class="btn btn-outline" type="submit">Ask to work it</button>
                    </form>
                </div>
            @endif
        </div>

        <aside class="rail">
            @include('leave.partials.requester')
            @include('leave.partials.balance', ['heading' => $own ? 'Your balance' : $request['employee_record']['name'].'’s balance'])
        </aside>
    </section>
@endsection
