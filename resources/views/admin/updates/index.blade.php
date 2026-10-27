@extends('layouts.app')

@php use App\Support\Updates; @endphp

@section('title', 'Updates')

@section('content')
    <div class="page-hd">
        <h1>Updates</h1>
        <p>
            After uploading a new version, apply its database changes here. A backup is
            taken first, every time.
        </p>
    </div>

    @if (session('update_output'))
        <section class="card">
            <div class="card-hd"><span class="card-title">What ran</span></div>
            <div class="card-body"><pre class="prose-quiet">{{ session('update_output') }}</pre></div>
        </section>
    @endif

    <section class="card">
        <div class="card-hd">
            <span class="card-title">{{ $pending === [] ? 'Up to date' : count($pending).' change'.(count($pending) === 1 ? '' : 's').' waiting' }}</span>
        </div>

        <div class="card-body">
            @if ($pending === [])
                <p class="prose-quiet">The database matches the uploaded version. Nothing to do.</p>
            @else
                <ul>
                    @foreach ($pending as $migration)
                        <li>{{ Updates::describe($migration) }}</li>
                    @endforeach
                </ul>

                <form method="POST" action="{{ route('admin.updates.store') }}">
                    @csrf
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Back up and apply</button>
                    </div>
                </form>

                <p class="prose-quiet">
                    Pages that use the new parts may show an error until this is applied, so
                    apply it straight after uploading.
                </p>
            @endif
        </div>
    </section>
@endsection
