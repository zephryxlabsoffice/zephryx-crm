{{-- The red "This wasn't me" block, when the mail carries one. See
     App\Support\Auth\SecurityLock. --}}
@if (($notMeUrl ?? '') !== '')
    <p style="margin: 24px 0 8px; color: #5C6B65;">
        Did not ask for this? Press the red button: your account is put on hold and
        signed out everywhere, and the administrators are told.
    </p>
    @include('mail.partials.alarm-button', ['url' => $notMeUrl])
@endif
