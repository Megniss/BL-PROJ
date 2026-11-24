<template>
  <transition name="email-prompt">
    <div v-if="visible" class="email-prompt" role="dialog" aria-labelledby="email-prompt-title" data-testid="email-prompt">
      <button class="email-prompt-close" @click="dismiss" :aria-label="t('emailPrompt.later')">×</button>
      <div class="email-prompt-icon" aria-hidden="true">📬</div>
      <div class="email-prompt-body">
        <p id="email-prompt-title" class="email-prompt-title">{{ t('emailPrompt.title') }}</p>
        <p class="email-prompt-text">{{ t('emailPrompt.text') }}</p>
        <div class="email-prompt-actions">
          <button class="btn btn-primary btn-sm" @click="goSettings">{{ t('emailPrompt.choose') }}</button>
          <button class="btn btn-outline-secondary btn-sm" @click="dismiss">{{ t('emailPrompt.later') }}</button>
        </div>
      </div>
    </div>
  </transition>
</template>

<script>
import axios from 'axios'
import langMixin from '../langMixin.js'
import { authStore, updateUser } from '../authStore.js'

// aicinājums ieslēgt grāmatu e-pastus (līdzīgi kā cookie paziņojums)
// rāda vienreiz: kamēr lietotājs nav ieslēdzis nevienu e-pastu un nav spiedis "ne tagad"
// atzīme glabājas serverī (users.email_prompted), tāpēc citā ierīcē vairs neparādās

// šajās lapās nerādām (iestatījumos jau ir slēdži, pārējās nav vietā)
const SKIP_ROUTES = ['admin', 'settings', 'login', 'register', 'forgotPassword', 'resetPassword']

export default {
  name: 'EmailPrompt',
  mixins: [langMixin],
  props: {
    // kamēr redzams cookie paziņojums, gaidām
    blocked: Boolean,
  },

  data() {
    return { authStore, visible: false, checking: false, timer: null }
  },

  watch: {
    'authStore.user.id'() { this.check() },
    '$route.name'() {
      if (SKIP_ROUTES.includes(this.$route.name)) this.visible = false
      this.check()
    },
    blocked() { this.check() },
  },

  mounted() {
    this.check()
  },

  beforeUnmount() {
    clearTimeout(this.timer)
  },

  methods: {
    async check() {
      const user = this.authStore.user
      if (!user || this.blocked || this.visible || this.checking) return
      if (user.email_prompted || SKIP_ROUTES.includes(this.$route.name)) return
      // vispirms 2FA ieteikums (Dashboard), lai nav divi logi uzreiz
      if (!user.two_factor_prompted && !user.two_factor_enabled) return

      // localStorage lietotājs var būt vecs - jautājam serverim
      this.checking = true
      try {
        const { data } = await axios.get('/api/me')
        updateUser(data)
        if (data.email_prompted) return
        // mazliet vēlāk, lai neuzlec uzreiz ar lapu
        this.timer = setTimeout(() => {
          if (this.authStore.user && !SKIP_ROUTES.includes(this.$route.name)) this.visible = true
        }, 1500)
      } catch { /* ignorē */ } finally {
        this.checking = false
      }
    },

    async dismiss() {
      this.visible = false
      if (this.authStore.user) updateUser({ ...this.authStore.user, email_prompted: true })
      try { await axios.post('/api/profile/email-prompt/dismiss') } catch { /* ignorē */ }
    },

    async goSettings() {
      await this.dismiss()
      this.$router.push({ name: 'settings', hash: '#emails' })
    },
  },
}
</script>
