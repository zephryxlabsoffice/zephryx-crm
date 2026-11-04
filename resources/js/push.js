/**
 * "Turn on notifications on this device". Each browser asks once, from a click,
 * because a permission prompt nobody asked for is the one everybody refuses.
 */
function keyBytes(base64Url) {
    const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(base64Url.length / 4) * 4, '=');

    return Uint8Array.from(atob(base64), (character) => character.charCodeAt(0));
}

function send(url, endpoint) {
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ endpoint }),
    }).then((response) => {
        if (!response.ok) {
            throw new Error(`The site answered ${response.status}`);
        }
    });
}

async function initPushPanel(panel) {
    const button = panel.querySelector('[data-push-button]');
    const status = panel.querySelector('[data-push-status]');

    const show = (text, buttonLabel = null) => {
        status.textContent = text;
        button.hidden = buttonLabel === null;
        button.textContent = buttonLabel ?? '';
    };

    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        show('This browser cannot receive them. On an iPhone or iPad, first add this site to your Home Screen and open it from there.');

        return;
    }

    const registration = await navigator.serviceWorker.register('/sw.js');
    let subscription = await registration.pushManager.getSubscription();

    const render = () => {
        if (Notification.permission === 'denied') {
            show('Blocked in this browser. Allow notifications for this site in the browser settings, then reload.');
        } else if (subscription) {
            show('On for this device.', 'Turn off');
        } else {
            show('Off for this device.', 'Turn on');
        }
    };

    // A browser that changes hands follows whoever is signed in now.
    if (subscription && Notification.permission === 'granted') {
        send(panel.dataset.pushSubscribe, subscription.endpoint).catch(() => {});
    }

    render();

    button.addEventListener('click', async () => {
        button.disabled = true;

        try {
            if (subscription) {
                await send(panel.dataset.pushUnsubscribe, subscription.endpoint);
                await subscription.unsubscribe();
                subscription = null;
            } else if ((await Notification.requestPermission()) === 'granted') {
                const created = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: keyBytes(panel.dataset.pushKey),
                });

                await send(panel.dataset.pushSubscribe, created.endpoint);
                subscription = created;
            }

            render();
        } catch {
            show('That did not work. Please try again.', subscription ? 'Turn off' : 'Turn on');
        } finally {
            button.disabled = false;
        }
    });
}

function initPush() {
    document.querySelectorAll('[data-push-panel]').forEach((panel) => {
        initPushPanel(panel).catch(() => {
            panel.querySelector('[data-push-status]').textContent = 'Notifications could not be set up in this browser.';
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPush);
} else {
    initPush();
}
