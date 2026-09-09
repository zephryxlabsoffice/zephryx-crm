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
        </div>

        <aside class="rail">
            @include('leave.partials.requester')
            @include('leave.partials.balance', ['heading' => $own ? 'Your balance' : $request['employee_record']['name'].'’s balance'])
        </aside>
    </section>
@endsection
