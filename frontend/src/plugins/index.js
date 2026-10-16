// src/plugins/index.js
import globalComponents from './global'
import notifyPlugin, { notify } from './notify'
import axiosPlugin, { api } from './axios'
import echoPlugin, { echo } from './echo'

export default {
  install(app) {
    app.use(globalComponents)
    app.use(notifyPlugin)
    app.use(axiosPlugin)
    app.use(echoPlugin)
  },
}

export { globalComponents, notify, api, echo }
