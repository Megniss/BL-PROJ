<template>
  <div class="settings-page">
    <AppNavbar />

    <div class="container py-5 px-3 px-md-4" style="max-width:680px">
      <h1 class="settings-title mb-4">{{ t('settings.title') }}</h1>

      <!-- profila rediģēšana -->
      <div class="settings-card mb-4">
        <h2 class="settings-section-title">{{ t('settings.editProfile') }}</h2>

        <form @submit.prevent="saveProfile">
          <div class="mb-3">
            <label class="form-label fw-semibold">{{ t('profile.fieldName') }}</label>
            <input v-model="profileForm.name" class="form-control" type="text" required />
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">{{ t('profile.fieldEmail') }}</label>
            <input v-model="profileForm.email" class="form-control" type="email" required />
          </div>

          <div class="settings-divider mb-3">{{ t('profile.changePwd') }} <span>{{ t('profile.changePwdHint') }}</span></div>

          <div class="mb-3">
            <label class="form-label fw-semibold">{{ t('profile.currentPwd') }}</label>
            <input v-model="profileForm.current_password" class="form-control" type="password" autocomplete="current-password" />
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label fw-semibold">{{ t('profile.newPwd') }}</label>
              <input v-model="profileForm.new_password" class="form-control" type="password" autocomplete="new-password" />
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">{{ t('profile.confirmPwd') }}</label>
              <input v-model="profileForm.new_password_confirmation" class="form-control" type="password" autocomplete="new-password" />
            </div>
          </div>

          <div v-if="profileError" class="alert alert-danger py-2 px-3 mb-3">{{ profileError }}</div>
          <div v-if="profileSuccess" class="alert alert-success py-2 px-3 mb-3">{{ profileSuccess }}</div>

          <button type="submit" class="btn btn-primary" :disabled="profileSaving">
            {{ profileSaving ? t('profile.saving') : t('profile.save') }}
          </button>
        </form>
      </div>

      <!-- privātuma iestatījumi -->
      <div class="settings-card mb-4">
        <h2 class="settings-section-title">{{ t('settings.privacy') }}</h2>

        <div class="d-flex flex-column gap-2">
          <label class="privacy-toggle">
            <span class="privacy-toggle-label">{{ t('profile.showJoined') }}</span>
            <input type="checkbox" v-model="privacyForm.show_joined" @change="savePrivacy" />
            <span class="privacy-toggle-switch"></span>
          </label>
          <label class="privacy-toggle">
            <span class="privacy-toggle-label">{{ t('profile.showSwaps') }}</span>
            <input type="checkbox" v-model="privacyForm.show_swaps" @change="savePrivacy" />
            <span class="privacy-toggle-switch"></span>
          </label>
        </div>
      </div>

      <!-- bloķētie lietotāji -->
      <div class="settings-card">
        <h2 class="settings-section-title">{{ t('settings.blocked') }}</h2>

        <div v-if="blockedLoading" class="text-muted small py-2">{{ t('profile.loading') }}</div>

        <div v-else-if="blockedUsers.length === 0" class="text-muted small py-2">{{ t('settings.noBlocked') }}</div>

        <div v-else class="d-flex flex-column gap-2">
          <div v-for="user in blockedUsers" :key="user.id" class="settings-blocked-row">
            <div class="settings-blocked-avatar">{{ user.name[0].toUpperCase() }}</div>
            <span class="settings-blocked-name">{{ user.name }}</span>
            <button class="btn btn-sm btn-outline-secondary ms-auto" @click="unblock(user)">{{ t('settings.unblock') }}</button>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script>
import axios from 'axios'
import { authStore, updateUser } from '../authStore.js'
import AppNavbar from './AppNavbar.vue'
import langMixin from '../langMixin.js'

export default {
  name: 'Settings',

  components: { AppNavbar },

  mixins: [langMixin],

  data() {
    return {
      authStore,

      profileForm: {
        name: '',
        email: '',
        current_password: '',
        new_password: '',
        new_password_confirmation: '',
      },
      profileSaving: false,
      profileError: '',
      profileSuccess: '',

      privacyForm: { show_joined: true, show_swaps: true },

      blockedUsers: [],
      blockedLoading: true,
    }
  },

  async mounted() {
    const [profile] = await Promise.all([
      axios.get('/api/profile'),
      this.fetchBlocked(),
    ])
    this.profileForm.name = profile.data.name
    this.profileForm.email = profile.data.email
    this.privacyForm.show_joined = profile.data.show_joined
    this.privacyForm.show_swaps = profile.data.show_swaps
  },

  methods: {
    async saveProfile() {
      this.profileError = ''
      this.profileSuccess = ''
      this.profileSaving = true
      try {
        const { data } = await axios.patch('/api/profile', this.profileForm)
        updateUser(data)
        this.profileForm.name = data.name
        this.profileForm.email = data.email
        this.profileForm.current_password = ''
        this.profileForm.new_password = ''
        this.profileForm.new_password_confirmation = ''
        this.profileSuccess = this.t('profile.saved')
      } catch (err) {
        const errors = err.response?.data?.errors
        this.profileError = errors
          ? Object.values(errors).flat()[0]
          : err.response?.data?.message || 'Something went wrong.'
      } finally {
        this.profileSaving = false
      }
    },

    async savePrivacy() {
      try {
        await axios.patch('/api/profile/privacy', this.privacyForm)
      } catch { /* ignore */ }
    },

    async fetchBlocked() {
      this.blockedLoading = true
      try {
        const { data } = await axios.get('/api/blocks')
        this.blockedUsers = data
      } finally {
        this.blockedLoading = false
      }
    },

    async unblock(user) {
      try {
        await axios.delete(`/api/blocks/${user.id}`)
        this.blockedUsers = this.blockedUsers.filter(u => u.id !== user.id)
      } catch { /* ignore */ }
    },
  }
}
</script>
