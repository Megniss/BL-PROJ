import { reactive } from 'vue'

export const themeStore = reactive({
  dark: false,

  toggle() {
    this.dark = !this.dark
    const theme = this.dark ? 'dark' : 'light'
    localStorage.setItem('theme', theme)
    document.documentElement.setAttribute('data-theme', theme)
  },

  // izpilda ielādējoties
  init() {
    const saved = localStorage.getItem('theme') || 'light'
    this.dark = saved === 'dark'
    document.documentElement.setAttribute('data-theme', saved)
  }
})
