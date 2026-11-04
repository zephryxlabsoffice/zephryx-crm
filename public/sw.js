/*
 * Shows a push notification. See App\Support\WebPush: the push arrives empty, so
 * the words are fetched from this site as whoever is signed in. If nobody is, it
 * shows a generic line and the announcement stays behind the sign-in page.
 */
const GENERIC = {
    title: 'ZephryxLabs CRM',
    body: 'There is something new for you. Open the CRM to read it.',
    url: '/',
};

async function latestNotification() {
    try {
        const response = await fetch('/push/latest', {
            credentials: 'same-origin',
            redirect: 'error',
            headers: { Accept: 'application/json' },
        });

        return response.ok ? await response.json() : GENERIC;
    } catch {
        return GENERIC;
    }
}

self.addEventListener('push', (event) => {
    event.waitUntil(latestNotification().then((message) => self.registration.showNotification(message.title, {
        body: message.body,
        icon: '/favicon.ico',
        tag: message.url,
        data: { url: message.url },
    })));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    // Only ever this site, whatever the message carried.
    const target = new URL(event.notification.data?.url ?? '/', self.location.origin);
    const url = target.origin === self.location.origin ? target.href : self.location.origin;

    event.waitUntil(self.clients.matchAll({ type: 'window' }).then((windows) => {
        const open = windows.find((client) => client.url === url);

        return open ? open.focus() : self.clients.openWindow(url);
    }));
});
