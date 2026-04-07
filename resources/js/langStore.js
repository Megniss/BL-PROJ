import { reactive } from 'vue'
import { translations } from './translations.js'

const saved = typeof localStorage !== 'undefined'
  ? localStorage.getItem('bookloop_locale')
  : null

export const langStore = reactive({
  locale: (saved && ['en', 'lv'].includes(saved)) ? saved : 'en'
})

export function t(key) {
  return translations[langStore.locale]?.[key]
    ?? translations['en']?.[key]
    ?? key
}

export function setLocale(locale) {
  langStore.locale = locale
  localStorage.setItem('bookloop_locale', locale)
}
