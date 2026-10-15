// src/plugins/index.js
import globalComponents from './global'

export default {
  install(app) {
    app.use(globalComponents)
  },
}

export { globalComponents }
