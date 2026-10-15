// src/plugins/index.js
import globalComponents from './global'
import notifyPlugin, { notify } from './notify'
import axiosPlugin, { api } from './axios'

export default {
  install(app) {
    app.use(globalComponents)
    app.use(notifyPlugin)
    app.use(axiosPlugin)
  },
}

export { globalComponents, notify, api }
