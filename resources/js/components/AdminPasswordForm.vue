<template>
  <form @submit.prevent="save">
    <div v-if="!mustChange" class="mb-3">
      <label for="admin-current-pwd" class="form-label fw-semibold">{{ t('adminAuth.current') }}</label>
      <input id="admin-current-pwd" v-model="form.current_password" class="form-control" type="password" autocomplete="off" required />
    </div>
    <div class="mb-3">
      <label for="admin-new-pwd" class="form-label fw-semibold">{{ t('adminAuth.new') }}</label>
      <input id="admin-new-pwd" v-model="form.new_password" class="form-control" type="password" autocomplete="new-password" minlength="12" required />
      <small class="text-muted">{{ t('adminAuth.hint') }}</small>
    </div>
    <div class="mb-3">
      <label for="admin-confirm-pwd" class="form-label fw-semibold">{{ t('adminAuth.confirm') }}</label>
      <input id="admin-confirm-pwd" v-model="form.new_password_confirmation" class="form-control" type="password" autocomplete="new-password" required />
    </div>

    <div v-if="error" class="alert alert-danger py-2 px-3">{{ error }}</div>

    <div class="d-flex justify-content-end gap-2 mt-4">
      <button v-if="!mustChange" type="button" class="btn btn-outline-secondary" @click="$emit('cancel')">{{ t('settings.deleteCancel') }}</button>
      <button type="submit" class="btn btn-primary" :disabled="saving">
        {{ saving ? t('profile.saving') : t('profile.save') }}
      </button>
    </div>
  </form>
</template>

<script>
import axios from 'axios'
import langMixin from '../langMixin.js'

export default {
  name: 'AdminPasswordForm',
  mixins: [langMixin],
  props: { mustChange: Boolean },
  emits: ['changed', 'cancel'],

  data() {
    return {
      form: { current_password: '', new_password: '', new_password_confirmation: '' },
      error: '',
      saving: false,
    }
  },

  methods: {
    async save() {
      this.error = ''
      if (this.form.new_password.length < 12) {
        this.error = this.t('adminAuth.hint')
        return
      }
      if (this.form.new_password !== this.form.new_password_confirmation) {
        this.error = this.t('adminAuth.mismatch')
        return
      }

      this.saving = true
      try {
        await axios.patch('/api/admin/password', this.form)
        this.$emit('changed')
      } catch (err) {
        const errors = err.response?.data?.errors
        this.error = errors?.current_password ? this.t('adminAuth.wrongCurrent') : this.t('dash.genericError')
      } finally {
        this.saving = false
      }
    },
  },
}
</script>
