let swRegistration = null;
let vapidPublicKey = null;

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)));
}

async function subscribe() {
    if (!swRegistration) {
        return false;
    }

    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        return false;
    }

    try {
        const subscription = await swRegistration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
        });

        const response = await fetch('/webpush/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
            },
            body: JSON.stringify(subscription),
        });

        if (!response.ok) {
            console.error('Push subscribe failed:', await response.text());
            return false;
        }

        return true;
    } catch (e) {
        console.error('Push subscription error:', e);
        return false;
    }
}

window.subscribeToPush = subscribe;

window.getSubscriptionStatus = function () {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        return Promise.resolve('unsupported');
    }

    return navigator.serviceWorker.ready.then((registration) => {
        return registration.pushManager.getSubscription().then((subscription) => {
            if (!subscription) {
                if (Notification.permission === 'denied') {
                    return 'denied';
                }
                return 'unsubscribed';
            }

            return 'subscribed';
        });
    });
};

export function registerSW() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        console.warn('Push not supported in this browser');
        return;
    }

    vapidPublicKey = document.querySelector('meta[name="vapid-public-key"]')?.getAttribute('content');

    if (!vapidPublicKey) {
        console.warn('VAPID public key not found');
        return;
    }

    navigator.serviceWorker.register('/sw.js').then((registration) => {
        swRegistration = registration;

        registration.pushManager.getSubscription().then((subscription) => {
            if (subscription) {
                return;
            }

            if (Notification.permission === 'granted') {
                subscribe();
            }
        });
    }).catch((e) => {
        console.error('Service worker registration failed:', e);
    });
}
