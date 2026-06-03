import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { registerSW } from './push-subscription.js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
});

registerSW();
