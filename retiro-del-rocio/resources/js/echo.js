import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Guarded: a build that ran without the Vite build-time env vars set (see
// the Dockerfile's `assets` stage) bakes in a literal `undefined` wsHost —
// `new Echo(...)` throws synchronously on that, which halts the rest of
// this module's *importer* (app.js's `import './bootstrap'` is its first
// line), silently skipping every Alpine component app.js registers after
// it. Nothing in this codebase actually consumes `window.Echo` yet, so
// degrading to "no realtime" here is safe — it must never be the thing that
// takes the whole site's booking flows down with it.
const wsHost = import.meta.env.VITE_REVERB_HOST;
if (wsHost) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
} else {
    console.warn('Echo: VITE_REVERB_HOST is not set at build time — realtime is disabled.');
}
