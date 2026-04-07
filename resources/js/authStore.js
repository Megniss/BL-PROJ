import { reactive } from 'vue'
import axios from 'axios'

const savedUser = localStorage.getItem('bookloop_user')
const savedToken = localStorage.getItem('bookloop_token')

export const authStore = reactive({
  user: savedUser ? JSON.parse(savedUser) : null,
  token: savedToken ? savedToken : null,
})

if (authStore.token) {
  axios.defaults.headers.common['Authorization'] = `Bearer ${authStore.token}`
}

export function setAuth(user, token) {
  authStore.user = user
  authStore.token = token
  localStorage.setItem('bookloop_user', JSON.stringify(user))
  localStorage.setItem('bookloop_token', token)
  axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
}

export function clearAuth() {
  authStore.user = null
  authStore.token = null
  localStorage.removeItem('bookloop_user')
  localStorage.removeItem('bookloop_token')
  delete axios.defaults.headers.common['Authorization']
}

export function updateUser(user) {
  authStore.user = user
  localStorage.setItem('bookloop_user', JSON.stringify(user))
}

export function isLoggedIn() {
  return !!authStore.token
}
