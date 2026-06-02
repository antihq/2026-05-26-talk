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
        self.registration.showNotification(title, options).then(() => {
            if (typeof data.notification?.data?.unread_count === 'number') {
                self.navigator.setAppBadge?.(data.notification.data.unread_count);
            }
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const url = event.notification.data?.url || '/';

    event.waitUntil(openURL(url));
});

async function openURL(url) {
    const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

    self.navigator.clearAppBadge?.();

    const existingClient = clients[0];

    if (existingClient) {
        await existingClient.navigate(url);
    } else {
        await self.clients.openWindow(url);
    }
}
