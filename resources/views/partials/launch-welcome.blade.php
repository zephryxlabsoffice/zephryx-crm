{{--
    The launch-day welcome, once per person per launch date (App\Support\Launch).

    A <dialog> closed by a form with method="dialog": no script at all, which
    the CSP would refuse inline anyway. The confetti is pure CSS and stops for
    anybody who asked for reduced motion.
--}}
<dialog class="launch" open aria-labelledby="launch-title">
    <div class="launch-confetti" aria-hidden="true">
        @for ($i = 0; $i < 24; $i++)<span></span>@endfor
    </div>

    <div class="launch-card">
        <div class="launch-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/>
                <path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/>
                <path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>
            </svg>
        </div>

        <h2 class="launch-title" id="launch-title">We are live!</h2>

        <p class="launch-text">
            From today {{ config('zephryx.brand.name') }} {{ config('zephryx.brand.suffix') }}
            is where we work: attendance, leave, tasks, tickets and payslips.
        </p>
        <p class="launch-text">
            <strong>Check in when you start and check out when you finish.</strong>
            A working day without a check-in now counts as absent.
        </p>

        <form method="dialog">
            <button class="btn btn-primary" type="submit">Let's go</button>
        </form>
    </div>
</dialog>
