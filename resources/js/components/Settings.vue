<template>
  <div class="settings-page">
    <AppNavbar />

    <div class="container py-5 px-3 px-md-4" style="max-width:680px">
      <h1 class="settings-title mb-4">{{ t('settings.title') }}</h1>

      <!-- profila info un parole -->
      <div class="settings-card mb-4">
        <h2 class="settings-section-title">{{ t('settings.editProfile') }}</h2>

        <form @submit.prevent="saveProfile">
          <div class="mb-3">
            <label for="settings-name" class="form-label fw-semibold">{{ t('profile.fieldName') }}</label>
            <input id="settings-name" v-model="profileForm.name" class="form-control" type="text" required />
          </div>
          <div class="mb-3">
            <label for="settings-email" class="form-label fw-semibold">{{ t('profile.fieldEmail') }}</label>
            <input id="settings-email" v-model="profileForm.email" class="form-control" type="email" required />
          </div>

          <div class="settings-divider mb-3">{{ t('profile.changePwd') }} <span>{{ t('profile.changePwdHint') }}</span></div>

          <div class="mb-3">
            <label for="settings-current-pwd" class="form-label fw-semibold">{{ t('profile.currentPwd') }}</label>
            <input id="settings-current-pwd" v-model="profileForm.current_password" class="form-control" type="password" autocomplete="current-password" />
          </div>
          <div class="row g-3 mb-3">
            <div class="col-12 col-sm-6">
              <label for="settings-new-pwd" class="form-label fw-semibold">{{ t('profile.newPwd') }}</label>
              <input id="settings-new-pwd" v-model="profileForm.new_password" class="form-control" type="password" autocomplete="new-password" />
              <small class="text-muted">{{ t('profile.pwdHint') }}</small>
            </div>
            <div class="col-12 col-sm-6">
              <label for="settings-confirm-pwd" class="form-label fw-semibold">{{ t('profile.confirmPwd') }}</label>
              <input id="settings-confirm-pwd" v-model="profileForm.new_password_confirmation" class="form-control" type="password" autocomplete="new-password" />
            </div>
          </div>

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
          <label class="privacy-toggle">
            <span class="privacy-toggle-label">{{ t('profile.showSwapHistory') }}</span>
            <input type="checkbox" v-model="privacyForm.show_swap_history" @change="savePrivacy" />
            <span class="privacy-toggle-switch"></span>
          </label>
        </div>
      </div>

      <!-- divpakāpju verifikācija -->
      <div class="settings-card mb-4">
        <h2 class="settings-section-title">🔐 {{ t('twofa.promptTitle') }}</h2>
        <p class="text-muted small mb-3">{{ t('twofa.settingsText') }}</p>

        <template v-if="twoFactor.enabled">
          <p class="small mb-3"><span class="tag tag-green">{{ t('twofa.on') }}</span></p>
          <form v-if="twoFactor.disabling" class="d-flex flex-wrap gap-2 align-items-start" @submit.prevent="disableTwoFactor">
            <div style="flex:1;min-width:200px">
              <input
                v-model="twoFactor.password"
                class="form-control"
                :class="{ 'is-invalid': twoFactor.error }"
                type="password"
                autocomplete="current-password"
                :placeholder="t('settings.deletePwd')"
                required
              />
              <div v-if="twoFactor.error" class="invalid-feedback">{{ twoFactor.error }}</div>
            </div>
            <button type="button" class="btn btn-outline-secondary" @click="twoFactor.disabling = false">{{ t('settings.deleteCancel') }}</button>
            <button type="submit" class="btn btn-outline-danger" :disabled="!twoFactor.password">{{ t('twofa.disableBtn') }}</button>
          </form>
          <button v-else class="btn btn-outline-danger" @click="twoFactor.disabling = true; twoFactor.password = ''; twoFactor.error = ''">{{ t('twofa.disableBtn') }}</button>
        </template>

        <template v-else>
          <TwoFactorSetup v-if="twoFactor.setup" @enabled="onTwoFactorEnabled" @cancel="twoFactor.setup = false" />
          <button v-else class="btn btn-primary" @click="twoFactor.setup = true">{{ t('twofa.enableBtn') }}</button>
        </template>
      </div>

      <!-- atrašanās vieta -->
      <div class="settings-card mb-4">
        <h2 class="settings-section-title">{{ t('location.title') }}</h2>
        <LocationPicker v-if="profileLoaded" :latitude="location.latitude" :longitude="location.longitude" />
      </div>

      <!-- bloķētie -->
      <div class="settings-card mb-4">
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

      <!-- konta dzēšana -->
      <div class="settings-card settings-card--danger">
        <h2 class="settings-section-title text-danger">{{ t('settings.deleteTitle') }}</h2>
        <p class="text-muted small mb-3">{{ t('settings.deleteText') }}</p>
        <p v-if="isOnlyAdmin" class="small mb-0">{{ t('settings.onlyAdmin') }}</p>
        <button v-else class="btn btn-outline-danger" @click="openDelete">{{ t('settings.deleteBtn') }}</button>
      </div>

    </div>
    <AppFooter />

    <Teleport to="body">
      <div v-if="deleteOpen" class="modal-overlay" @click.self="closeDelete">
        <form class="modal-card modal-card--sm" @submit.prevent="deleteAccount">
          <h2 class="modal-title mb-2">{{ t('settings.deleteConfirmTitle') }}</h2>
          <p class="modal-desc mb-3">{{ t('settings.deleteConfirmText') }}</p>

          <label for="delete-pwd" class="form-label fw-semibold">{{ t('settings.deletePwd') }}</label>
          <input
            id="delete-pwd"
            ref="deletePwd"
            v-model="deletePassword"
            class="form-control"
            :class="{ 'is-invalid': deleteError }"
            type="password"
            autocomplete="current-password"
            required
          />
          <div v-if="deleteError" class="invalid-feedback">{{ deleteError }}</div>

          <template v-if="isAdmin">
            <label for="delete-reason" class="form-label fw-semibold mt-3">{{ t('settings.deleteReason') }}</label>
            <textarea id="delete-reason" v-model="deleteReason" class="form-control" rows="2" maxlength="500" required></textarea>
          </template>

          <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" @click="closeDelete">{{ t('settings.deleteCancel') }}</button>
            <button type="submit" class="btn btn-danger" :disabled="!deletePassword || (isAdmin && !deleteReason.trim()) || deleting">
              {{ deleting ? t('settings.deleting') : t('settings.deleteBtn') }}
            </button>
          </div>
        </form>
      </div>
    </Teleport>
  </div>
</template>

<script>
import axios from 'axios'
import { authStore, updateUser, clearAuth } from '../authStore.js'
import { showToast } from '../toast.js'
import AppNavbar from './AppNavbar.vue'
import AppFooter from './AppFooter.vue'
import LocationPicker from './LocationPicker.vue'
import TwoFactorSetup from './TwoFactorSetup.vue'
import langMixin from '../langMixin.js'

export default {
  name: 'Settings',

  components: { AppNavbar, AppFooter, LocationPicker, TwoFactorSetup },

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

      privacyForm: { show_joined: true, show_swaps: true, show_swap_history: true },

      blockedUsers: [],
      blockedLoading: true,

      profileLoaded: false,
      twoFactor: { enabled: false, setup: false, disabling: false, password: '', error: '' },
      location: { latitude: null, longitude: null },
      isAdmin: false,
      isOnlyAdmin: false,
      deleteOpen: false,
      deletePassword: '',
      deleteReason: '',
      deleteError: '',
      deleting: false,
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
    this.privacyForm.show_swap_history = profile.data.show_swap_history
    this.isAdmin = profile.data.is_admin
    this.isOnlyAdmin = profile.data.is_only_admin
    this.location = { latitude: profile.data.latitude, longitude: profile.data.longitude }
    this.twoFactor.enabled = profile.data.two_factor_enabled
    this.profileLoaded = true
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
        showToast(this.t('profile.saved'))
      } catch (err) {
        const errors = err.response?.data?.errors
        const msg = errors ? Object.values(errors).flat()[0] : err.response?.data?.message || this.t('dash.genericError')
        showToast(msg, 'error')
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

    onTwoFactorEnabled() {
      this.twoFactor.enabled = true
      this.twoFactor.setup = false
      updateUser({ ...authStore.user, two_factor_enabled: true, two_factor_prompted: true })
      showToast(this.t('twofa.enabled'))
    },

    async disableTwoFactor() {
      this.twoFactor.error = ''
      try {
        await axios.post('/api/two-factor/disable', { password: this.twoFactor.password })
        this.twoFactor.enabled = false
        this.twoFactor.disabling = false
        updateUser({ ...authStore.user, two_factor_enabled: false })
        showToast(this.t('twofa.disabled'))
      } catch (err) {
        this.twoFactor.error = err.response?.status === 422 ? this.t('twofa.wrongPassword') : this.t('dash.genericError')
      }
    },

    openDelete() {
      this.deleteOpen = true
      this.deletePassword = ''
      this.deleteReason = ''
      this.deleteError = ''
      this.$nextTick(() => this.$refs.deletePwd?.focus())
    },

    closeDelete() {
      if (this.deleting) return
      this.deleteOpen = false
      this.deletePassword = ''
    },

    async deleteAccount() {
      this.deleting = true
      this.deleteError = ''
      try {
        await axios.delete('/api/profile', { data: { password: this.deletePassword, reason: this.deleteReason.trim() } })
        clearAuth()
        showToast(this.t('settings.deleted'))
        this.$router.push({ name: 'home' })
      } catch (err) {
        // servera kļūdas lietotājam nerāda
        const errors = err.response?.data?.errors
        this.deleteError = errors ? Object.values(errors).flat()[0] : this.t('dash.genericError')
      } finally {
        this.deleting = false
      }
    },
  }
}
</script>
