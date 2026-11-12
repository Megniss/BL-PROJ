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

        <!-- otrais solis - kods no e-pasta -->
        <template v-if="challenge">
          <h1 class="auth-title mb-1">{{ t('twofa.title') }}</h1>
          <p class="auth-sub mb-4">{{ t('twofa.sent') }}</p>

          <form @submit.prevent="verifyCode">
            <div class="mb-3">
              <label for="login-code" class="form-label fw-semibold">{{ t('twofa.code') }}</label>
              <input
                id="login-code"
                ref="codeInput"
                v-model="code"
                class="form-control twofa-code-input"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                placeholder="000000"
                required
              />
            </div>

            <div class="form-check mb-3">
              <input id="login-remember" v-model="remember" class="form-check-input" type="checkbox" />
              <label for="login-remember" class="form-check-label">{{ t('twofa.remember') }}</label>
            </div>

            <div v-if="errorMsg" class="alert alert-danger py-2 px-3 mb-3">{{ errorMsg }}</div>
            <div v-if="infoMsg" class="alert alert-success py-2 px-3 mb-3">{{ infoMsg }}</div>

            <button type="submit" class="btn btn-primary w-100 py-2" :disabled="loading || code.length < 6">
              {{ loading ? t('login.loading') : t('twofa.verify') }}
            </button>
          </form>

          <div class="d-flex justify-content-between mt-3">
            <a class="forgot-link" @click="backToLogin">← {{ t('twofa.back') }}</a>
            <a class="forgot-link" @click="resendCode">{{ t('twofa.resend') }}</a>
          </div>
        </template>

        <template v-else>
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
        </template>
        <a class="auth-back mt-2" @click="$router.push({ name: 'home' })">{{ t('login.back') }}</a>
      </div>
    </div>

    <!-- adminam jāizvēlas, kur doties -->
    <Teleport to="body">
      <div v-if="adminChoice" class="modal-overlay">
        <div class="modal-card modal-card--sm">
          <template v-if="!adminUnlock">
            <h2 class="modal-title mb-2">{{ t('adminAuth.chooseTitle') }}</h2>
            <p class="modal-desc mb-4">{{ t('adminAuth.chooseText') }}</p>
            <div class="d-flex flex-column gap-2">
              <button class="btn btn-primary" @click="adminUnlock = true">{{ t('adminAuth.panel') }}</button>
              <button class="btn btn-outline-secondary" @click="$router.push({ name: 'dashboard' })">{{ t('adminAuth.profile') }}</button>
            </div>
          </template>

          <template v-else>
            <h2 class="modal-title mb-2">{{ t('adminAuth.panel') }}</h2>
            <p class="modal-desc mb-3">{{ t('adminAuth.enterText') }}</p>
            <AdminUnlock cancelable @unlocked="$router.push({ name: 'admin' })" @cancel="adminUnlock = false" />
          </template>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script>
import axios from 'axios'
import langMixin from '../langMixin.js'
import { setAuth } from '../authStore.js'
import AdminUnlock from './AdminUnlock.vue'
import { getDeviceToken, saveDeviceToken } from '../trustedDevice.js'

export default {
  name: 'Login',

  components: { AdminUnlock },

  mixins: [langMixin],

  data() {
    return {
      form: { email: '', password: '' },
      loading: false,
      errorMsg: '',
      adminChoice: false,
      adminUnlock: false,
      challenge: null,
      code: '',
      remember: false,
      infoMsg: '',
    }
  },

  methods: {
    async handleLogin() {
      this.errorMsg = ''
      this.loading = true
      try {
        const { data } = await axios.post('/api/login', {
          ...this.form,
          device_token: getDeviceToken(this.form.email),
        })

        if (data.two_factor) {
          this.challenge = data.challenge
          this.code = ''
          this.$nextTick(() => this.$refs.codeInput?.focus())
          return
        }

        this.finishLogin(data)
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
    },

    finishLogin(data) {
      setAuth(data.user, data.token)

      if (data.user.is_admin) {
        this.adminChoice = true
      } else {
        this.$router.push({ name: 'dashboard' })
      }
    },

    async verifyCode() {
      this.errorMsg = ''
      this.infoMsg = ''
      this.loading = true
      try {
        const { data } = await axios.post('/api/login/verify', {
          challenge: this.challenge,
          code: this.code,
          remember: this.remember,
        })
        if (data.device_token) saveDeviceToken(this.form.email, data.device_token)
        this.finishLogin(data)
      } catch (e) {
        const data = e.response?.data
        if (data?.error === 'expired') {
          // kods novecojis vai par daudz kļūdu - sāk no paroles
          this.backToLogin()
          this.errorMsg = this.t('twofa.expired')
        } else if (data?.blocked) {
          this.errorMsg = this.t('login.errorBlocked')
        } else {
          this.errorMsg = this.t('twofa.wrong')
          this.code = ''
        }
      } finally {
        this.loading = false
      }
    },

    async resendCode() {
      this.errorMsg = ''
      this.infoMsg = ''
      try {
        await axios.post('/api/login/resend', { challenge: this.challenge })
        this.infoMsg = this.t('twofa.resent')
      } catch (e) {
        if (e.response?.data?.error === 'expired') {
          this.backToLogin()
          this.errorMsg = this.t('twofa.expired')
        } else {
          this.errorMsg = this.t('twofa.resendWait')
        }
      }
    },

    backToLogin() {
      this.challenge = null
      this.code = ''
      this.form.password = ''
      this.infoMsg = ''
    }
  }
}
</script>
