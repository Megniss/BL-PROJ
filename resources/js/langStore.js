import { reactive } from 'vue'
import { translations } from './translations.js'
import axios from 'axios'

const saved = typeof localStorage !== 'undefined'
  ? localStorage.getItem('bookloop_locale')
  : null

export const langStore = reactive({
  locale: saved || 'en',
  // starts with static fallback; replaced by DB once fetched
  languages: [
    { code: 'en', name: 'English', flag: 'gb' },
    { code: 'lv', name: 'Latvian', flag: 'lv' },
  ],
  overrides: {}, // { 'en': { 'nav.home': 'Home' }, 'lv': { ... } }
})

export function t(key) {
  const override = langStore.overrides[langStore.locale]?.[key]
  if (override !== undefined && override !== '') return override
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
    // validate saved locale against active languages
    if (!data.find(l => l.code === langStore.locale)) {
      langStore.locale = 'en'
      localStorage.setItem('bookloop_locale', 'en')
    }
  } catch { /* keep static fallback */ }

  // fetch overrides for current locale
  await _fetchOverrides(langStore.locale)
}

// called by admin after saving translations to refresh the cache
export function invalidateOverrides(code) {
  delete langStore.overrides[code]
}
