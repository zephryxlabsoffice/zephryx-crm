@extends('layouts.verify')

@section('title', 'Verify an employee')

@section('content')
    <section class="vf-hero">
        <h1>Verify a {{ config('zephryx.brand.name') }} employee</h1>
        <p>
            Scan the QR code on their ID card, or type the Employee ID printed on it.
            You will see whether they work with us today.
        </p>

        <form class="vf-search" method="POST" action="{{ route('verify.search') }}">
            @csrf
            <label class="sr-only" for="vf-id">Employee ID</label>
            <input id="vf-id" name="employee_id" type="text" required maxlength="32"
                   placeholder="Employee ID, e.g. ZEPH261001" autocomplete="off" autocapitalize="characters"
                   spellcheck="false" value="{{ old('employee_id') }}">
            <button class="btn btn-primary" type="submit">Verify</button>
        </form>

        @error('employee_id')
            <p class="field-error vf-search-error">{{ $message }}</p>
        @enderror
    </section>

    @include('verify.partials.contact')
@endsection
