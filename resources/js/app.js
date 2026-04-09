import 'bootstrap'
import 'flag-icons/css/flag-icons.min.css'
import { createApp } from 'vue'
import axios from 'axios'
import App from './components/App.vue'
import router from './router/router'
import { clearAuth, isLoggedIn } from './authStore.js'
import { themeStore } from './themeStore.js'

themeStore.init()

// if the token expires the server returns 401 — log the user out automatically
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

// PWA service worker
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
  })
}
