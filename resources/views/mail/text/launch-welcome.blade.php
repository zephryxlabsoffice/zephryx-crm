Hello,

From today, {!! $day !!}, we work in {!! config('zephryx.brand.name') !!} {!! config('zephryx.brand.suffix') !!}: your attendance, leave, tasks, tickets and payslips all live there.

Check in when you start work and check out when you finish. From today a working day without a check-in counts as absent.

Sign in: {!! $url !!}

Sign in with your email or your Employee ID. Forgotten your password? Use "Forgot password" on the sign-in page.

@include('mail.text.footer')
