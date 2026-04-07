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
        <div class="d-flex align-items-center gap-2 mb-4 d-lg-none">
          <span class="brand-icon">⇄</span>
          <span class="brand-name">BookLoop</span>
        </div>

        <h1 class="auth-title mb-1">{{ t('reset.title') }}</h1>
        <p class="auth-sub mb-4">{{ t('reset.sub') }}</p>

        <div v-if="success" class="alert alert-success">
          {{ t('reset.success') }}
          <a @click="$router.push({ name: 'login' })" class="fw-semibold ms-1" style="cursor:pointer">{{ t('reset.loginLink') }}</a>
        </div>

        <form v-else @submit.prevent="handleSubmit">
          <div class="mb-3">
            <label class="form-label fw-semibold">{{ t('login.password') }}</label>
            <input v-model="form.password" type="password" class="form-control" placeholder="••••••••" required minlength="8" />
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">{{ t('register.confirm') }}</label>
            <input v-model="form.password_confirmation" type="password" class="form-control" placeholder="••••••••" required />
          </div>

          <div v-if="errorMsg" class="alert alert-danger py-2 px-3 mb-3">{{ errorMsg }}</div>

          <button type="submit" class="btn btn-primary w-100 py-2" :disabled="loading">
            {{ loading ? t('reset.loading') : t('reset.btn') }}
          </button>
        </form>
      </div>
    </div>

  </div>
</template>

<script>
import axios from 'axios'
import langMixin from '../langMixin.js'

export default {
  name: 'ResetPassword',

  mixins: [langMixin],

  data() {
    return {
      form: { password: '', password_confirmation: '' },
      loading: false,
      success: false,
      errorMsg: '',
    }
  },

  computed: {
    token() { return this.$route.query.token || '' },
    email() { return this.$route.query.email || '' },
  },

  methods: {
    async handleSubmit() {
      if (this.form.password !== this.form.password_confirmation) {
        this.errorMsg = this.t('register.errorMatch')
        return
      }

      this.errorMsg = ''
      this.loading = true

      try {
        await axios.post('/api/reset-password', {
          token: this.token,
          email: this.email,
          password: this.form.password,
          password_confirmation: this.form.password_confirmation,
        })
        this.success = true
      } catch (err) {
        this.errorMsg = err.response?.data?.message || this.t('register.errorGeneral')
      } finally {
        this.loading = false
      }
    }
  }
}
</script>
