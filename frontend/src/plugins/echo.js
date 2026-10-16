import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

export const echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY || 'reverb_key_customer_intel',
  wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname || 'localhost',
  wsPort: Number(import.meta.env.VITE_REVERB_PORT) || 8080,
  wssPort: Number(import.meta.env.VITE_REVERB_PORT) || 8080,
  forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
  enabledTransports: ['ws', 'wss'],
})

export default {
  install(app) {
    app.config.globalProperties.$echo = echo
    app.provide('echo', echo)
  },
}
