import { createRouter, createWebHistory } from 'vue-router'
import { isLoggedIn, authStore } from '../authStore.js'

import Home from '../components/Home.vue'
import About from '../components/About.vue'
import Login from '../components/Login.vue'
import Register from '../components/Register.vue'
import ForgotPassword from '../components/ForgotPassword.vue'
import ResetPassword from '../components/ResetPassword.vue'

const routes = [
  { path: '/', name: 'home', component: Home },
  { path: '/about', name: 'about', component: About },
  { path: '/login', name: 'login', component: Login, meta: { guestOnly: true } },
  { path: '/register', name: 'register', component: Register, meta: { guestOnly: true } },
  { path: '/forgot-password', name: 'forgotPassword', component: ForgotPassword, meta: { guestOnly: true } },
  { path: '/reset-password', name: 'resetPassword', component: ResetPassword, meta: { guestOnly: true } },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('../components/Dashboard.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/profile',
    name: 'profile',
    component: () => import('../components/Profile.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/messages',
    name: 'messages',
    component: () => import('../components/Messages.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/settings',
    name: 'settings',
    component: () => import('../components/Settings.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/browse',
    name: 'browse',
    component: () => import('../components/Browse.vue'),
  },
  {
    path: '/users/:id',
    name: 'userProfile',
    component: () => import('../components/UserProfile.vue'),
  },
  {
    path: '/support',
    name: 'support',
    component: () => import('../components/Support.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/admin',
    name: 'admin',
    component: () => import('../components/Admin.vue'),
    meta: { requiresAdmin: true },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'notFound',
    component: () => import('../components/NotFound.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  if (to.meta.requiresAuth && !isLoggedIn()) {
    return { name: 'login' }
  }
  if (to.meta.guestOnly && isLoggedIn()) {
    return { name: 'dashboard' }
  }
  if (to.meta.requiresAdmin && (!isLoggedIn() || !authStore.user?.is_admin)) {
    return { name: 'home' }
  }
})

export default router
