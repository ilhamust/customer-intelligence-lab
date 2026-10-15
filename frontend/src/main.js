import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import plugins from './plugins'
import './app.css'
import App from './App.vue'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(plugins)

app.mount('#app')
