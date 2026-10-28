{{-- Inline styles and tables: see mail/layout.blade.php. --}}
@extends('mail.layout')

@section('preheader', 'From today, everything happens in '.config('zephryx.brand.name').'.')

@section('heading', 'Welcome to '.config('zephryx.brand.name').' '.config('zephryx.brand.suffix'))

@section('content')
    <p style="margin: 0 0 16px;">Hello,</p>

    <p style="margin: 0 0 16px;">
        From today, {{ $day }}, we work in {{ config('zephryx.brand.name') }}
        {{ config('zephryx.brand.suffix') }}: your attendance, leave, tasks, tickets and
        payslips all live there.
    </p>

    <p style="margin: 0 0 20px;">
        <strong>Check in when you start work and check out when you finish.</strong>
        From today a working day without a check-in counts as absent.
    </p>

    @include('mail.partials.button', ['url' => $url, 'label' => 'Sign in'])

    <p style="margin: 0; color: #5C6B65;">
        Sign in with your email or your Employee ID. Forgotten your password? Use
        "Forgot password" on the sign-in page.
    </p>
@endsection
