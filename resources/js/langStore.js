import { reactive } from 'vue'
import { translations } from './translations.js'
import axios from 'axios'

const saved = typeof localStorage !== 'undefined'
  ? localStorage.getItem('bookloop_locale')
  : null

export const langStore = reactive({
  locale: saved || 'en',
  // static fallback until we get the real list from DB
  languages: [
    { code: 'en', name: 'English', flag: 'gb' },
    { code: 'lv', name: 'Latvian', flag: 'lv' },
  ],
  overrides: {}, // { 'en': { 'nav.home': 'Home' }, 'lv': { ... } }
})

export function t(key) {
  const override = langStore.overrides[langStore.locale]?.[key]
  if (override !== undefined && override !== '') return override
  // fall back to static file, then english, then just the key
  return translations[langStore.locale]?.[key]
    ?? translations['en']?.[key]
    ?? key
}

export async function setLocale(locale) {
  langStore.locale = locale
  localStorage.setItem('bookloop_locale', locale)
  await _fetchOverrides(locale)
}

async function _fetchOverrides(code) {
  // skip if we already loaded this language
  if (langStore.overrides[code] !== undefined) return
  try {
    const { data } = await axios.get(`/api/translations/${code}`)
    langStore.overrides[code] = data
  } catch {
    langStore.overrides[code] = {}
  }
}

export async function initLanguages() {
  try {
    const { data } = await axios.get('/api/languages')
    langStore.languages = data
    // make sure saved locale is still valid
    if (!data.find(l => l.code === langStore.locale)) {
      langStore.locale = 'en'
      localStorage.setItem('bookloop_locale', 'en')
    }
  } catch { /* keep static fallback */ }

  await _fetchOverrides(langStore.locale)
}

// call this after admin saves translations so we re-fetch
export function invalidateOverrides(code) {
  delete langStore.overrides[code]
}
