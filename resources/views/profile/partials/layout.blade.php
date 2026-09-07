@extends('layouts.app')

{{--
    The frame all four profile pages share: header, tabs, panel, rail.

    A layout rather than four copies, because the header and rail are identical
    on every one and the tabs have to agree about which is current. Each page
    fills in `panel`.
--}}

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>My Profile</h1>
            <p>What you can change about yourself, and what the company holds.</p>
        </div>

        {{-- No "Edit Profile" button.

             The handover had one sitting above a form whose fields were already
             editable, with Save and Cancel at the bottom — so it either did
             nothing or it did the same thing twice. The form is the form. --}}
    </div>

    @include('profile.partials.header')

    <nav class="tabs pf-tabs" aria-label="Profile sections">
        @foreach ($tabs as $key => $meta)
            <a class="tab @if ($tab === $key) active @endif"
               href="{{ route($meta['route']) }}"
               @if ($tab === $key) aria-current="page" @endif>
                {{ $meta['label'] }}
            </a>
        @endforeach
    </nav>

    <section class="pf-grid">
        <div class="pf-main">
            @yield('panel')
        </div>

        <aside class="rail">
            @include('profile.partials.summary')
            @include('profile.partials.documents')
            @include('profile.partials.hr-owned')
        </aside>
    </section>
@endsection
