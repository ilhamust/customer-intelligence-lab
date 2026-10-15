// src/plugins/notify.js
import Toast, { useToast, POSITION } from 'vue-toastification'
import 'vue-toastification/dist/index.css'

const options = {
  position: POSITION.TOP_RIGHT,
  timeout: 4000,
  closeOnClick: true,
  pauseOnFocusLoss: true,
  pauseOnHover: true,
  draggable: true,
  showCloseButtonOnHover: false,
  hideProgressBar: false,
}

export const notify = {
  success: (msg, opts) => useToast().success(msg, opts),
  error: (msg, opts) => useToast().error(msg, opts),
  info: (msg, opts) => useToast().info(msg, opts),
  warning: (msg, opts) => useToast().warning(msg, opts),
}

export default {
  install(app) {
    app.use(Toast, options)
    app.config.globalProperties.$notify = notify
    app.provide('notify', notify)
  },
}
