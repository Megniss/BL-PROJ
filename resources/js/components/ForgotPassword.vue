<template>
  <div class="auth-page">

    <div class="auth-left">
      <div class="auth-left-inner">
        <div class="auth-brand">
          <span class="brand-icon" style="font-size:32px">⇄</span>
          <span class="brand-name" style="color:#fff; font-size:24px">BookLoop</span>
        </div>
        <h2 class="auth-left-title">{{ t('auth.leftTitle') }}</h2>
        <p class="auth-left-sub">{{ t('auth.leftSub') }}</p>
        <ul class="auth-perks">
          <li>📚 {{ t('auth.perk1') }}</li>
          <li>🔍 {{ t('auth.perk2') }}</li>
          <li>🤝 {{ t('auth.perk3') }}</li>
        </ul>
      </div>
    </div>

    <div class="auth-right">
      <div class="auth-form-wrap">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div class="d-flex align-items-center gap-2 d-lg-none">
            <span class="brand-icon">⇄</span>
            <span class="brand-name">BookLoop</span>
          </div>
          <div class="d-flex align-items-center gap-1 ms-auto">
            <button :class="['lang-btn', { active: langStore.locale === 'en' }]" @click="setLocale('en')">EN</button>
            <span class="lang-divider">|</span>
            <button :class="['lang-btn', { active: langStore.locale === 'lv' }]" @click="setLocale('lv')">LV</button>
          </div>
        </div>

        <h1 class="auth-title mb-1">{{ t('forgot.title') }}</h1>
        <p class="auth-sub mb-4">{{ t('forgot.sub') }}</p>

        <div v-if="resetUrl" class="alert alert-success">
          {{ t('forgot.success') }}<br>
          <a :href="resetUrl" class="fw-semibold">{{ t('forgot.clickHere') }}</a>
        </div>

        <form v-if="!resetUrl" @submit.prevent="handleSubmit">
          <div class="mb-3">
            <label class="form-label fw-semibold">{{ t('login.email') }}</label>
            <input v-model="email" type="email" class="form-control" placeholder="you@example.com" required />
          </div>

          <div v-if="errorMsg" class="alert alert-danger py-2 px-3 mb-3">{{ errorMsg }}</div>

          <button type="submit" class="btn btn-primary w-100 py-2" :disabled="loading">
            {{ loading ? t('forgot.loading') : t('forgot.btn') }}
          </button>
        </form>

        <p class="auth-switch mt-3">
          <a @click="$router.push({ name: 'login' })">{{ t('forgot.backToLogin') }}</a>
        </p>
      </div>
    </div>

  </div>
</template>

<script>
import axios from 'axios'
import langMixin from '../langMixin.js'

export default {
  name: 'ForgotPassword',

  mixins: [langMixin],

  data() {
    return {
      email: '',
      loading: false,
      resetUrl: '',
      errorMsg: '',
    }
  },

  methods: {
    async handleSubmit() {
      this.errorMsg = ''
      this.loading = true

      try {
        const res = await axios.post('/api/forgot-password', { email: this.email })
        this.resetUrl = res.data.reset_url
      } catch (err) {
        this.errorMsg = err.response?.data?.message || this.t('register.errorGeneral')
      } finally {
        this.loading = false
      }
    }
  }
}
</script>
