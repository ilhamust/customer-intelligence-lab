// src/router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import appRoutes from './routes/app.route'

const routes = [
  {
    path: '/',
    redirect: '/app/conversations',
  },
  ...appRoutes,
  {
    path: '/:pathMatch(.*)*',
    redirect: '/app/conversations',
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
