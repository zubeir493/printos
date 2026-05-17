self.addEventListener('push', (event) => {
    if (! event.data) {
        return;
    }

    const payload = event.data.json();
    const title = payload.title || 'PrintOS';

    delete payload.title;

    event.waitUntil(self.registration.showNotification(title, payload));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url === targetUrl && 'focus' in client) {
                    return client.focus();
                }
            }

            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }

            return null;
        }),
    );
});
