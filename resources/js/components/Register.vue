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

        <h1 class="auth-title mb-1">{{ t('register.title') }}</h1>
        <p class="auth-sub mb-4">{{ t('register.sub') }}</p>

        <form @submit.prevent="handleRegister">
          <div class="mb-3">
            <label for="reg-name" class="form-label fw-semibold">{{ t('register.name') }}</label>
            <input id="reg-name" v-model="form.name" type="text" class="form-control" :placeholder="t('register.namePlaceholder')" required />
          </div>
          <div class="mb-3">
            <label for="reg-email" class="form-label fw-semibold">{{ t('register.email') }}</label>
            <input id="reg-email" v-model="form.email" type="email" class="form-control" placeholder="you@example.com" required />
          </div>
          <div class="mb-3">
            <label for="reg-password" class="form-label fw-semibold">{{ t('register.password') }}</label>
            <input id="reg-password" v-model="form.password" type="password" class="form-control" :placeholder="t('register.passwordPlaceholder')" minlength="8" required />
            <small class="text-muted">{{ t('profile.pwdHint') }}</small>
          </div>
          <div class="mb-3">
            <label for="reg-confirm" class="form-label fw-semibold">{{ t('register.confirm') }}</label>
            <input id="reg-confirm" v-model="form.passwordConfirm" type="password" class="form-control" placeholder="••••••••" required />
          </div>

          <div v-if="errorMsg" class="alert alert-danger py-2 px-3 mb-3">{{ errorMsg }}</div>

          <button type="submit" class="btn btn-primary w-100 py-2" :disabled="loading">
            {{ loading ? t('register.loading') : t('register.btn') }}
          </button>
        </form>

        <p class="auth-switch mt-3">
          {{ t('register.switch') }}
          <a @click="$router.push({ name: 'login' })">{{ t('register.switchLink') }}</a>
        </p>
        <a class="auth-back mt-2" @click="$router.push({ name: 'home' })">{{ t('register.back') }}</a>
      </div>
    </div>

  </div>
</template>

<script>
import axios from 'axios'
import langMixin from '../langMixin.js'
import { setAuth } from '../authStore.js'

export default {
  name: 'Register',

  mixins: [langMixin],

  data() {
    return {
      form: { name: '', email: '', password: '', passwordConfirm: '' },
      loading: false,
      errorMsg: ''
    }
  },

  methods: {
    async handleRegister() {
      this.errorMsg = ''

      if (this.form.password !== this.form.passwordConfirm) {
        this.errorMsg = this.t('register.errorMatch')
        return
      }

      this.loading = true
      try {
        const { data } = await axios.post('/api/register', {
          name: this.form.name,
          email: this.form.email,
          password: this.form.password,
          password_confirmation: this.form.passwordConfirm,
        })
        setAuth(data.user, data.token)
        this.$router.push({ name: 'dashboard' })
      } catch (err) {
        const errors = err.response?.data?.errors
        this.errorMsg = errors?.email?.[0] || this.t('register.errorGeneral')
      } finally {
        this.loading = false
      }
    }
  }
}
</script>
