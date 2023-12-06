import { createRouter, createWebHistory } from 'vue-router'

const routes = [{
  path: '/',
  name: 'tickets',
  component: () => import('@pages/Tickets.vue'),
  props: route => ({ id: route.query.id }),
  alias: ['/tickets'],
  children: [{
    path: '?id=:id',
    name: 'ticket',
    props: true,
    component: () => import('@pages/Ticket.vue')
  }, {
    path: '/new',
    name: 'new_ticket',
    component: () => import('@temps/NewTicket.vue')
  }]
}, {
  path: '/archive',
  name: 'archive',
  component: () => import('@pages/Archive.vue'),
  children: [{
    path: '?id=:id',
    name: 'archive_ticket',
    props: true,
    component: () => import('@pages/Ticket.vue')
  }]
}, {
  path: '/details',
  name: 'details',
  component: () => import('@pages/Details.vue')
}, {
  path: '/dashboard',
  name: 'dashboard',
  component: () => import('@pages/Dashboard.vue')
}, {
  path: '/coupons',
  name: 'coupons',
  component: () => import('@pages/Coupons.vue')
}, {
  path: '/groups',
  name: 'groups',
  component: () => import('@pages/Groups.vue')
  // }, {
  //   path: '/roles',
  //   name: 'roles',
  //   component: () => import('@pages/Roles.vue')
}, {
  path: '/reasons',
  name: 'reasons',
  component: () => import('@pages/Reasons.vue')
}, {
  path: '/users',
  name: 'users',
  component: () => import('@pages/Users.vue')
}, {
  path: '/:pathMatch(.*)',
  redirect: '/',
}]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
