
<template>
  <div>
    <div class="scroll-progress" :style="{ width: scrollProgress + '%' }"></div>
    <router-view></router-view>
    <button v-if="showBackToTop" class="back-to-top" @click="scrollToTop" aria-label="Atgriezties uz augšu">↑</button>

    <div v-if="showCookie" class="cookie-banner" role="alert">
      <p class="cookie-text">{{ t('cookie.text') }}</p>
      <button class="cookie-btn" @click="acceptCookie">{{ t('cookie.btn') }}</button>
    </div>

    <ToastContainer />
  </div>
</template>

<script>
import langMixin from '../langMixin.js'
import { initLanguages } from '../langStore.js'
import ToastContainer from './ToastContainer.vue'

export default {
  name: 'App',

  components: { ToastContainer },

  mixins: [langMixin],

  data() {
    return {
      scrollProgress: 0,
      showBackToTop: false,
      showCookie: false,
    }
  },

  mounted() {
    initLanguages()
    window.addEventListener('scroll', this.onScroll, { passive: true })
    // rāda katru reizi, vienkāršāk tā
    this.showCookie = true
  },

  beforeUnmount() {
    window.removeEventListener('scroll', this.onScroll)
  },

  methods: {
    onScroll() {
      const scrolled = window.scrollY
      const total = document.documentElement.scrollHeight - window.innerHeight
      this.scrollProgress = total > 0 ? (scrolled / total) * 100 : 0
      this.showBackToTop = scrolled > 400
    },

    scrollToTop() {
      window.scrollTo({ top: 0, behavior: 'smooth' })
    },

    acceptCookie() {
      this.showCookie = false
    },
  }
}
</script>
