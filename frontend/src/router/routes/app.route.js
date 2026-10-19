// src/router/routes/app.route.js
export default [
  {
    path: '/app',
    name: 'App',
    component: () => import('@/layouts/app/AppIndex.vue'),
    redirect: { name: 'Conversations' },
    children: [
      {
        path: 'conversations',
        name: 'Conversations',
        component: () => import('@/pages/app/conversations/ConversationListPage.vue'),
      },
      {
        path: 'devices',
        name: 'Devices',
        component: () => import('@/pages/app/devices/DeviceListPage.vue'),
      },
      {
        path: 'customers',
        name: 'Customers',
        component: () => import('@/pages/app/customers/CustomerListPage.vue'),
      },
    ],
  },
]
