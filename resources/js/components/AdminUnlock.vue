<template>
  <form @submit.prevent="unlock">
    <label for="admin-unlock-pwd" class="form-label fw-semibold">{{ t('adminAuth.password') }}</label>
    <input
      id="admin-unlock-pwd"
      ref="pwd"
      v-model="password"
      class="form-control"
      :class="{ 'is-invalid': error }"
      type="password"
      autocomplete="off"
      required
    />
    <div v-if="error" class="invalid-feedback">{{ error }}</div>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <button v-if="cancelable" type="button" class="btn btn-outline-secondary" @click="$emit('cancel')">{{ t('adminAuth.back') }}</button>
      <button type="submit" class="btn btn-primary" :disabled="!password || loading">
        {{ loading ? t('adminAuth.checking') : t('adminAuth.open') }}
      </button>
    </div>
  </form>
</template>

<script>
import axios from 'axios'
import langMixin from '../langMixin.js'

export default {
  name: 'AdminUnlock',
  mixins: [langMixin],
  props: { cancelable: Boolean },
  emits: ['unlocked', 'cancel'],

  data() {
    return { password: '', error: '', loading: false }
  },

  mounted() {
    this.$refs.pwd?.focus()
  },

  methods: {
    async unlock() {
      this.loading = true
      this.error = ''
      try {
        const { data } = await axios.post('/api/admin/unlock', { admin_password: this.password })
        this.$emit('unlocked', data)
      } catch (err) {
        const data = err.response?.data
        if (data?.throttled) {
          this.error = this.t('adminAuth.throttled').replace('{m}', data.minutes)
        } else if (err.response?.status === 422) {
          this.error = this.t('adminAuth.wrong')
        } else {
          this.error = this.t('dash.genericError')
        }
        this.password = ''
      } finally {
        this.loading = false
      }
    },
  },
}
</script>
