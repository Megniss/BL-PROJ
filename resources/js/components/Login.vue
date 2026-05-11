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

        <h1 class="auth-title mb-1">{{ t('login.title') }}</h1>
        <p class="auth-sub mb-4">{{ t('login.sub') }}</p>

        <form @submit.prevent="handleLogin">
          <div class="mb-3">
            <label for="login-email" class="form-label fw-semibold">{{ t('login.email') }}</label>
            <input id="login-email" v-model="form.email" type="email" class="form-control" placeholder="you@example.com" required />
          </div>

          <div class="mb-2">
            <label for="login-password" class="form-label fw-semibold">{{ t('login.password') }}</label>
            <input id="login-password" v-model="form.password" type="password" class="form-control" placeholder="••••••••" required />
          </div>

          <div class="text-end mb-3">
            <a class="forgot-link" @click="$router.push({ name: 'forgotPassword' })">{{ t('login.forgot') }}</a>
          </div>

          <div v-if="errorMsg" class="alert alert-danger py-2 px-3 mb-3">{{ errorMsg }}</div>

          <button type="submit" class="btn btn-primary w-100 py-2" :disabled="loading">
            {{ loading ? t('login.loading') : t('login.btn') }}
          </button>
        </form>

        <p class="auth-switch mt-3">
          {{ t('login.switch') }}
          <a @click="$router.push({ name: 'register' })">{{ t('login.switchLink') }}</a>
        </p>
        <a class="auth-back mt-2" @click="$router.push({ name: 'home' })">{{ t('login.back') }}</a>
      </div>
    </div>

  </div>
</template>

<script>
import axios from 'axios'
import langMixin from '../langMixin.js'
import { setAuth } from '../authStore.js'

export default {
  name: 'Login',

  mixins: [langMixin],

  data() {
    return {
      form: { email: '', password: '' },
      loading: false,
      errorMsg: ''
    }
  },

  methods: {
    async handleLogin() {
      this.errorMsg = ''
      this.loading = true
      try {
        const { data } = await axios.post('/api/login', this.form)
        setAuth(data.user, data.token)
        this.$router.push({ name: 'dashboard' })
      } catch (e) {
        const data = e.response?.data
        if (data?.throttled) {
          this.errorMsg = this.t('login.errorThrottle').replace('{m}', data.minutes)
        } else if (data?.blocked) {
          this.errorMsg = this.t('login.errorBlocked')
        } else {
          this.errorMsg = this.t('login.error')
        }
      } finally {
        this.loading = false
      }
    }
  }
}
</script>
