<template>
  <nav class="bookloop-navbar">
    <div class="container-xl d-flex align-items-center py-2 gap-3">

      <!-- logo -->
      <div class="d-flex align-items-center gap-2 me-auto" role="button" tabindex="0"
        @click="$router.push({ name: 'home' })"
        @keyup.enter="$router.push({ name: 'home' })"
        style="cursor:pointer" aria-label="BookLoop — doties uz sākumlapu">
        <span class="brand-icon" aria-hidden="true">⇄</span>
        <span class="brand-name">BookLoop</span>
      </div>

      <div class="d-flex align-items-center gap-2">
        <button class="theme-btn" @click="themeStore.toggle()" :aria-label="themeStore.dark ? 'Ieslēgt gaišo režīmu' : 'Ieslēgt tumšo režīmu'">
          <span aria-hidden="true">{{ themeStore.dark ? '☀️' : '🌙' }}</span>
        </button>
        <div class="d-flex align-items-center gap-1">
          <button :class="['lang-btn', { active: langStore.locale === 'en' }]" @click="setLocale('en')">EN</button>
          <span class="lang-divider" aria-hidden="true">|</span>
          <button :class="['lang-btn', { active: langStore.locale === 'lv' }]" @click="setLocale('lv')">LV</button>
        </div>

        <!-- slot: page-specific items (notifications, back buttons etc.) -->
        <div class="d-none d-lg-flex align-items-center gap-2">
          <slot />
        </div>

        <!-- user avatar dropdown -->
        <div v-if="authStore.user" class="nav-user-wrap" ref="dropdownWrap">
          <button class="nav-avatar-btn" @click="dropdownOpen = !dropdownOpen" :aria-label="authStore.user.name">
            {{ initials }}
          </button>
          <div v-if="dropdownOpen" class="nav-dropdown">
            <div class="nav-dropdown-name">{{ authStore.user.name }}</div>
            <div class="nav-dropdown-divider"></div>
            <button class="nav-dropdown-item" @click="go('dashboard')">{{ t('nav.myLibrary') }}</button>
            <button class="nav-dropdown-item" @click="go('browse')">{{ t('nav.browse') }}</button>
            <button class="nav-dropdown-item" @click="go('messages')">{{ t('nav.messages') }}</button>
            <button class="nav-dropdown-item" @click="go('settings')">{{ t('nav.settings') }}</button>
            <div class="nav-dropdown-divider"></div>
            <button class="nav-dropdown-item nav-dropdown-logout" @click="logout">{{ t('nav.logout') }}</button>
          </div>
        </div>

        <!-- burger (mobile, guests only) -->
        <button v-if="!authStore.user" class="burger-btn d-lg-none" @click="menuOpen = !menuOpen" :aria-label="menuOpen ? 'Aizvērt izvēlni' : 'Atvērt izvēlni'">
          {{ menuOpen ? '✕' : '☰' }}
        </button>
      </div>
    </div>

    <div v-if="menuOpen" class="nav-mobile-menu d-lg-none">
      <slot />
    </div>
  </nav>
</template>

<script>
import langMixin from '../langMixin.js'
import { themeStore } from '../themeStore.js'
import { authStore, clearAuth } from '../authStore.js'
import axios from 'axios'

export default {
  name: 'AppNavbar',
  mixins: [langMixin],
  data() {
    return { themeStore, authStore, menuOpen: false, dropdownOpen: false }
  },

  computed: {
    initials() {
      if (!this.authStore.user?.name) return '?'
      return this.authStore.user.name
        .split(' ')
        .map(w => w[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
    },
  },

  mounted() {
    document.addEventListener('click', this.onDocClick)
  },

  beforeUnmount() {
    document.removeEventListener('click', this.onDocClick)
  },

  watch: {
    $route() {
      this.menuOpen    = false
      this.dropdownOpen = false
    }
  },

  methods: {
    go(name) {
      this.dropdownOpen = false
      this.$router.push({ name })
    },

    async logout() {
      this.dropdownOpen = false
      try { await axios.post('/api/logout') } finally {
        clearAuth()
        this.$router.push({ name: 'home' })
      }
    },

    onDocClick(e) {
      if (this.$refs.dropdownWrap && !this.$refs.dropdownWrap.contains(e.target)) {
        this.dropdownOpen = false
      }
    }
  }
}
</script>
