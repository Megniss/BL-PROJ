<template>
  <nav class="bookloop-navbar">
    <div class="container-xl d-flex align-items-center py-2 gap-3">

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
        <!-- buttons if 2 langs, dropdown if more -->
        <div class="d-flex align-items-center gap-1" v-if="langStore.languages.length <= 2">
          <template v-for="(lang, i) in langStore.languages" :key="lang.code">
            <span v-if="i > 0" class="lang-divider" aria-hidden="true">|</span>
            <button :class="['lang-btn', { active: langStore.locale === lang.code }]" @click="setLocale(lang.code)">
              <span v-if="lang.flag" :class="'fi fi-' + lang.flag" style="border-radius:2px"></span>
              <span v-else>{{ lang.code.toUpperCase() }}</span>
            </button>
          </template>
        </div>
        <div v-else class="lang-dropdown-wrap" ref="langWrap">
          <button class="lang-flag-btn" @click="langDropOpen = !langDropOpen" :aria-label="'Language: ' + currentLang.name">
            <span v-if="currentLang.flag" :class="'fi fi-' + currentLang.flag" style="border-radius:2px"></span>
            <span v-else>{{ currentLang.code.toUpperCase() }}</span>
            ▾
          </button>
          <div v-if="langDropOpen" class="lang-dropdown">
            <button
              v-for="lang in langStore.languages"
              :key="lang.code"
              class="lang-dropdown-item"
              :class="{ active: langStore.locale === lang.code }"
              @click="setLocale(lang.code); langDropOpen = false"
            >
              <span v-if="lang.flag" :class="'fi fi-' + lang.flag" style="border-radius:2px"></span>
              <span v-else>{{ lang.code.toUpperCase() }}</span>
              {{ lang.name }}
            </button>
          </div>
        </div>

        <!-- extra nav items from parent, hidden on mobile -->
        <div class="d-none d-lg-flex align-items-center gap-2">
          <slot />
        </div>

        <!-- stuff like the bell icon that should always show -->
        <slot name="inline" />

        <!-- avatar + dropdown, desktop only -->
        <div v-if="authStore.user" class="nav-user-wrap d-none d-lg-block" ref="dropdownWrap">
          <button class="nav-avatar-btn" @click="dropdownOpen = !dropdownOpen" :aria-label="authStore.user.name">
            {{ initials }}
          </button>
          <div v-if="dropdownOpen" class="nav-dropdown">
            <div class="nav-dropdown-name">{{ authStore.user.name }}</div>
            <div class="nav-dropdown-divider"></div>
            <button class="nav-dropdown-item" @click="go('profile')">{{ t('nav.profile') }}</button>
            <button class="nav-dropdown-item" @click="go('dashboard')">{{ t('nav.myLibrary') }}</button>
            <button class="nav-dropdown-item" @click="go('browse')">{{ t('nav.browse') }}</button>
            <button class="nav-dropdown-item" @click="go('messages')">{{ t('nav.messages') }}</button>
            <button class="nav-dropdown-item" @click="go('support')">{{ t('nav.support') }}</button>
            <button class="nav-dropdown-item" @click="go('settings')">{{ t('nav.settings') }}</button>
            <template v-if="authStore.user?.is_admin">
              <div class="nav-dropdown-divider"></div>
              <button class="nav-dropdown-item nav-dropdown-admin" @click="go('admin')">{{ t('nav.admin') }}</button>
            </template>
            <div class="nav-dropdown-divider"></div>
            <button class="nav-dropdown-item nav-dropdown-logout" @click="logout">{{ t('nav.logout') }}</button>
          </div>
        </div>

        <!-- burger menu for mobile -->
        <button class="burger-btn d-lg-none" @click="menuOpen = !menuOpen" :aria-label="menuOpen ? 'Aizvērt izvēlni' : 'Atvērt izvēlni'">
          {{ menuOpen ? '✕' : '☰' }}
        </button>
      </div>
    </div>

    <!-- mobile nav panel -->
    <div v-if="menuOpen" class="nav-mobile-menu d-lg-none">
      <slot />
      <template v-if="authStore.user">
        <div class="nav-mobile-divider mt-1"></div>
        <button class="nav-mob-link" @click="go('profile')">{{ t('nav.profile') }}</button>
        <button class="nav-mob-link" @click="go('dashboard')">{{ t('nav.myLibrary') }}</button>
        <button class="nav-mob-link" @click="go('browse')">{{ t('nav.browse') }}</button>
        <button class="nav-mob-link" @click="go('messages')">{{ t('nav.messages') }}</button>
        <button class="nav-mob-link" @click="go('support')">{{ t('nav.support') }}</button>
        <button class="nav-mob-link" @click="go('settings')">{{ t('nav.settings') }}</button>
        <button v-if="authStore.user?.is_admin" class="nav-mob-link nav-mob-admin" @click="go('admin')">{{ t('nav.admin') }}</button>
        <div class="nav-mobile-divider"></div>
        <button class="nav-mob-link nav-mob-logout" @click="logout">{{ t('nav.logout') }}</button>
      </template>
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
    return { themeStore, authStore, menuOpen: false, dropdownOpen: false, langDropOpen: false }
  },

  computed: {
    currentLang() {
      return this.langStore.languages.find(l => l.code === this.langStore.locale)
        ?? this.langStore.languages[0]
        ?? { code: 'en', name: 'English', flag: '🇬🇧' }
    },

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
      this.menuOpen = false
      this.dropdownOpen = false
    }
  },

  methods: {
    go(name) {
      this.dropdownOpen = false
      this.menuOpen = false
      this.$router.push({ name })
    },

    async logout() {
      this.dropdownOpen = false
      this.menuOpen = false
      try { await axios.post('/api/logout') } finally {
        clearAuth()
        this.$router.push({ name: 'home' })
      }
    },

    onDocClick(e) {
      if (this.$refs.dropdownWrap && !this.$refs.dropdownWrap.contains(e.target)) {
        this.dropdownOpen = false
      }
      if (this.$refs.langWrap && !this.$refs.langWrap.contains(e.target)) {
        this.langDropOpen = false
      }
    }
  }
}
</script>
