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
    ],
  },
]
