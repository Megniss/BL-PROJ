import 'bootstrap'
import 'flag-icons/css/flag-icons.min.css'
import { createApp } from 'vue'
import axios from 'axios'
import App from './components/App.vue'
import router from './router/router'
import { clearAuth, isLoggedIn } from './authStore.js'
import { themeStore } from './themeStore.js'

themeStore.init()

// Automātiski izraksta lietotāju ja serveris atgriež 401 (nederīgs vai beidzies tokens)
axios.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401 && isLoggedIn()) {
      clearAuth()
      router.push({ name: 'login' })
    }
    return Promise.reject(error)
  }
)

createApp(App).use(router).mount('#app')

// service worker reģistrācija (PWA)
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
  })
}