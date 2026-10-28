@extends('layouts.app')

@php use App\Support\Maintenance; use Illuminate\Support\Carbon; @endphp

@section('title', 'Maintenance')

@section('content')
    <div class="page-hd">
        <h1>Maintenance</h1>
        <p>
            While maintenance is on, nobody but this Admin Panel can use the site, so an
            update or a restore cannot clash with somebody else's work. Everybody with an
            active account is emailed.
        </p>
    </div>

    @if ($active)
        @include('partials.notice', [
            'tone' => 'warning',
            'title' => 'Maintenance is ON',
            'message' => 'Staff and clients see the maintenance page.'
                .($state['to'] ? ' It ends on its own at '.Carbon::parse($state['to'])->format('D j M, g:i A').'.' : ' It stays on until you turn it off below.'),
        ])
    @elseif ($state !== null)
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'Maintenance is scheduled',
            'message' => 'From '.Carbon::parse($state['from'])->format('D j M, g:i A').' to '.Carbon::parse($state['to'])->format('D j M, g:i A').'. The site stays open until then.',
        ])
    @endif

    @if ($state !== null)
        <section class="card">
            <div class="card-hd">
                <span class="card-title">{{ $active ? 'Turn it off' : 'Call it off' }}</span>
            </div>
            <div class="card-body">
                <p class="prose-quiet">
                    {{ $active
                        ? 'Opens the site again and emails everybody that it is back.'
                        : 'Cancels the schedule and emails everybody that it will not happen.' }}
                </p>
                <form method="POST" action="{{ route('admin.maintenance.end') }}">
                    @csrf
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">
                            {{ $active ? 'End maintenance, site is live' : 'Call off the scheduled maintenance' }}
                        </button>
                    </div>
                </form>
            </div>
        </section>
    @else
        <section class="card">
            <div class="card-hd"><span class="card-title">Now</span></div>
            <div class="card-body">
                <p class="prose-quiet">
                    Closes the site straight away and emails everybody that maintenance is under
                    way. It stays on until you turn it off here, which emails everybody again.
                </p>
                <form method="POST" action="{{ route('admin.maintenance.start') }}">
                    @csrf
                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="mn-now-note">Note for the email <span class="prose-quiet">(optional)</span></label>
                            <input id="mn-now-note" name="note" type="text" maxlength="300"
                                   placeholder="Uploading the new version" value="{{ old('note') }}">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Start maintenance now</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="card">
            <div class="card-hd"><span class="card-title">Schedule</span></div>
            <div class="card-body">
                <p class="prose-quiet">
                    Emails everybody the time it starts and ends. The site closes and reopens on
                    its own at those times. Times are office time (IST).
                </p>
                <form method="POST" action="{{ route('admin.maintenance.schedule') }}">
                    @csrf
                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="mn-from">From</label>
                            <input id="mn-from" name="from" type="datetime-local" required value="{{ old('from') }}">
                            @error('from')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-field">
                            <label class="form-field-lbl" for="mn-to">To</label>
                            <input id="mn-to" name="to" type="datetime-local" required value="{{ old('to') }}">
                            @error('to')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-field">
                            <label class="form-field-lbl" for="mn-note">Note for the email <span class="prose-quiet">(optional)</span></label>
                            <input id="mn-note" name="note" type="text" maxlength="300"
                                   placeholder="Moving to the new server" value="{{ old('note') }}">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Schedule and email everybody</button>
                    </div>
                </form>
            </div>
        </section>
    @endif
@endsection
