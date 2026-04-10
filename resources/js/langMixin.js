// mixin lai katrs komponents dabū t() bez tieša importa
import { langStore, t, setLocale } from './langStore.js'

export default {
  data() {
    return { langStore }
  },
  methods: {
    t(key) {
      return t(key)
    },
    async setLocale(locale) {
      await setLocale(locale)
    }
  }
}
