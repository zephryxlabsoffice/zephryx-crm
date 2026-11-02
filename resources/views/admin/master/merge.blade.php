@extends('layouts.app')

@section('title', 'Merge '.$item->name)

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('admin.master.show', $list) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                {{ $meta['label'] }}
            </a>
            <h1>Merge {{ $item->name }}</h1>
            <p>Everything using {{ $item->name }} moves to the row you choose, then {{ $item->name }} is retired.</p>
        </div>
    </div>

    <section class="card">
        <div class="card-hd">
            <span class="card-title">Who moves</span>
        </div>

        <div class="card-body">
            @if ($people === [])
                <p class="dash-note">Nobody is on {{ $item->name }}.</p>
            @else
                <p class="dash-note">{{ implode(', ', $people) }}</p>
            @endif

            @foreach ($accessChanges as $target => $people)
                <p class="dash-note"><strong>Merging into {{ $target }} changes access:</strong></p>
                @foreach ($people as $person)
                    <p class="dash-note">
                        {{ $person['name'] }}
                        @if ($person['gains'] !== []) gains {{ collect($person['gains'])->map(fn ($key) => $labels[$key] ?? $key)->join(', ') }}. @endif
                        @if ($person['loses'] !== []) loses {{ collect($person['loses'])->map(fn ($key) => $labels[$key] ?? $key)->join(', ') }}. @endif
                    </p>
                @endforeach
            @endforeach

            <p class="dash-note">There is no undo button. The audit log keeps these names, so it can be put back by hand.</p>

            <form method="POST" action="{{ route('admin.master.merge', $list) }}">
                @csrf
                <input type="hidden" name="item" value="{{ $item->id }}">

                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-field-lbl" for="md-into">Merge into</label>
                        <select id="md-into" name="into" required>
                            <option value="">Choose</option>
                            @foreach ($targets as $target)
                                <option value="{{ $target->id }}" @selected((int) old('into') === $target->id)>{{ $target->name }}</option>
                            @endforeach
                        </select>
                        @error('into') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Merge and retire {{ $item->name }}</button>
                </div>
            </form>
        </div>
    </section>
@endsection
