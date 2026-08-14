import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbHost = import.meta.env.VITE_REVERB_HOST || window.location.hostname || 'localhost';
const reverbPort = parseInt(import.meta.env.VITE_REVERB_PORT || '8085', 10);
const isHttps = (import.meta.env.VITE_REVERB_SCHEME === 'https') || (window.location.protocol === 'https:');

if (reverbKey) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: isHttps ? reverbPort : reverbPort,
        wssPort: reverbPort,
        forceTLS: isHttps,
        enabledTransports: isHttps ? ['wss'] : ['ws'],
        disableStats: true,
    });
}
