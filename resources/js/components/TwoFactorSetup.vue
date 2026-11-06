<template>
  <form @submit.prevent="confirm">
    <p class="small text-muted mb-3">{{ sending ? t('twofa.sending') : t('twofa.sentEnable') }}</p>

    <label for="twofa-setup-code" class="form-label fw-semibold">{{ t('twofa.code') }}</label>
    <input
      id="twofa-setup-code"
      ref="codeInput"
      v-model="code"
      class="form-control twofa-code-input"
      :class="{ 'is-invalid': error }"
      inputmode="numeric"
      autocomplete="one-time-code"
      maxlength="6"
      placeholder="000000"
      :disabled="!challenge"
      required
    />
    <div v-if="error" class="invalid-feedback">{{ error }}</div>

    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
      <a class="forgot-link me-auto" @click="send">{{ t('twofa.resend') }}</a>
      <button type="button" class="btn btn-outline-secondary" @click="$emit('cancel')">{{ t('settings.deleteCancel') }}</button>
      <button type="submit" class="btn btn-primary" :disabled="!challenge || code.length < 6 || saving">
        {{ saving ? '…' : t('twofa.enableBtn') }}
      </button>
    </div>
  </form>
</template>

<script>
import axios from 'axios'
import langMixin from '../langMixin.js'

// ieslēdzot 2FA vispirms pārbauda, ka kods no e-pasta tiešām pienāk
export default {
  name: 'TwoFactorSetup',
  mixins: [langMixin],
  emits: ['enabled', 'cancel'],

  data() {
    return { challenge: null, code: '', error: '', sending: false, saving: false }
  },

  mounted() {
    this.send()
  },

  methods: {
    async send() {
      this.error = ''
      this.sending = true
      try {
        const { data } = await axios.post('/api/two-factor/send')
        this.challenge = data.challenge
        this.code = ''
        this.$nextTick(() => this.$refs.codeInput?.focus())
      } catch (err) {
        this.error = err.response?.status === 429 ? this.t('twofa.resendWait') : this.t('twofa.mailError')
      } finally {
        this.sending = false
      }
    },

    async confirm() {
      this.error = ''
      this.saving = true
      try {
        await axios.post('/api/two-factor/enable', { challenge: this.challenge, code: this.code })
        this.$emit('enabled')
      } catch (err) {
        if (err.response?.data?.error === 'expired') {
          this.error = this.t('twofa.expiredResend')
          this.challenge = null
        } else {
          this.error = this.t('twofa.wrong')
        }
        this.code = ''
      } finally {
        this.saving = false
      }
    },
  },
}
</script>
