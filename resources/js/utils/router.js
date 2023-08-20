import { createRouter, createWebHistory } from 'vue-router'
import { defineAsyncComponent } from 'vue'

const routes = [{
  path: '/',
  name: 'tickets',
  component: defineAsyncComponent(() => import('@pages/Tickets.vue')),
  props: route => ({ id: route.query.id }),
  alias: ['/tickets'],
  children: [{
    path: ':id',
    name: 'ticket',
    props: true,
    component: defineAsyncComponent(() => import('@pages/Ticket.vue'))
  }, {
    path: '/new',
    name: 'new_ticket',
    component: defineAsyncComponent(() => import('@temps/NewTicket.vue'))
  }]
}, {
  path: '/archive',
  name: 'archive',
  component: defineAsyncComponent(() => import('@pages/Archive.vue')),
  children: [{
    path: ':id',
    name: 'archive_ticket',
    props: true,
    component: defineAsyncComponent(() => import('@pages/Ticket.vue'))
  }]
}, {
  path: '/details',
  name: 'details',
  component: defineAsyncComponent(() => import('@pages/Details.vue'))
}, {
  path: '/dashboard',
  name: 'dashboard',
  component: defineAsyncComponent(() => import('@pages/Dashboard.vue'))
}, {
  path: '/coupons',
  name: 'coupons',
  component: defineAsyncComponent(() => import('@pages/Coupons.vue'))
}, {
  path: '/groups',
  name: 'groups',
  component: defineAsyncComponent(() => import('@pages/Groups.vue'))
  // }, {
  //   path: '/roles',
  //   name: 'roles',
  //   component: defineAsyncComponent(() => import('@pages/Roles.vue'))
}, {
  path: '/reasons',
  name: 'reasons',
  component: defineAsyncComponent(() => import('@pages/Reasons.vue'))
}, {
  path: '/users',
  name: 'users',
  component: defineAsyncComponent(() => import('@pages/Users.vue'))
}, {
  path: '/:pathMatch(.*)',
  redirect: '/',
}]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
