import { langStore, t, setLocale } from './langStore.js'

export default {
  data() {
    return { langStore }
  },
  methods: {
    t(key) {
      return t(key)
    },
    setLocale(locale) {
      setLocale(locale)
    }
  }
}
