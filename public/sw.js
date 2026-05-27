self.addEventListener('push', function (event) {
    if (!event.data) {
        console.warn('Push received with no data');
        return;
    }

    const payload = event.data.text();

    if (!payload) {
        console.warn('Push received with empty data');
        return;
    }

    let data;

    try {
        data = JSON.parse(payload);
    } catch (e) {
        data = { title: 'Campfire', body: payload };
    }

    let title, options;

    if (data.web_push === 8030 && data.notification) {
        title = data.notification.title || 'Campfire';
        options = {
            body: data.notification.body || '',
            icon: data.notification.icon || '/favicon.ico',
            badge: data.notification.badge || '/favicon.ico',
            data: {
                url: data.notification.navigate || '/',
            },
        };
    } else {
        title = data.title || 'Campfire';
        options = {
            body: data.body || '',
            icon: data.icon || '/favicon.ico',
            badge: data.badge || '/favicon.ico',
            data: {
                url: data.url || '/',
            },
        };
    }

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const url = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windowClients) {
            for (const client of windowClients) {
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});
