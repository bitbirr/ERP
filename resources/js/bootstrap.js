import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

window.Echo = new Echo({
  broadcaster: 'pusher',
  key: import.meta.env.VITE_PUSHER_APP_KEY || import.meta.env.MIX_PUSHER_APP_KEY,
  cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || import.meta.env.MIX_PUSHER_APP_CLUSTER,
  wsHost: import.meta.env.VITE_PUSHER_HOST ?? 'ws-' + (import.meta.env.VITE_PUSHER_APP_CLUSTER || 'eu') + '.pusher.com',
  wsPort: import.meta.env.VITE_PUSHER_PORT ? Number(import.meta.env.VITE_PUSHER_PORT) : 443,
  wssPort: import.meta.env.VITE_PUSHER_PORT ? Number(import.meta.env.VITE_PUSHER_PORT) : 443,
  forceTLS: true,
  enabledTransports: ['ws','wss'],
})
