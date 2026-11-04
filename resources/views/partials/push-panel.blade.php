{{--
    Turning push on for this device. The button stays hidden until
    resources/js/push.js has worked out what this browser can do.
--}}
<div class="push-panel" data-push-panel
     data-push-key="{{ app(\App\Support\WebPush::class)->publicKey() }}"
     data-push-subscribe="{{ route('push.subscribe') }}"
     data-push-unsubscribe="{{ route('push.unsubscribe') }}">
    <div class="push-panel-text">
        <strong>Notifications on this device</strong>
        <span>Pop-up alerts for new announcements, even when the CRM is closed.</span>
        <span class="push-panel-status" data-push-status>Checking this browser...</span>
    </div>
    <button class="btn btn-outline btn-sm" type="button" data-push-button hidden></button>
</div>
