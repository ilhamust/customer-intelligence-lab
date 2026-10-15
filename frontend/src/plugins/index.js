// src/plugins/index.js
import globalComponents from './global'
import notifyPlugin, { notify } from './notify'

export default {
  install(app) {
    app.use(globalComponents)
    app.use(notifyPlugin)
  },
}

export { globalComponents, notify }
