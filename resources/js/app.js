import { registerSW } from './push-subscription.js';

registerSW();

navigator.serviceWorker.addEventListener('message', (event) => {
    if (event.data?.type === 'update-badge' && typeof event.data.count === 'number') {
        navigator.setAppBadge?.(event.data.count);
    } else if (event.data?.type === 'clear-badge') {
        navigator.clearAppBadge?.();
    }
});
