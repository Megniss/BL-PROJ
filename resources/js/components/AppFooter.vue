<template>
  <footer class="bookloop-footer py-4">
    <div class="container-xl d-flex flex-wrap align-items-center gap-4">
      <div class="d-flex align-items-center gap-2 flex-grow-1">
        <span class="brand-icon" style="color:#5a9b5e">⇄</span>
        <span class="brand-name text-white">BookLoop</span>
        <span class="footer-tagline ms-2">{{ t('footer.tagline') }}</span>
      </div>
      <div class="footer-links d-flex gap-4">
        <button class="footer-link-btn" @click="$router.push({ name: 'about' })">{{ t('footer.about') }}</button>
        <button class="footer-link-btn" @click="goHowItWorks">{{ t('footer.howItWorks') }}</button>
        <button class="footer-link-btn" @click="$router.push({ name: 'browse' })">{{ t('footer.browse') }}</button>
      </div>
      <p class="footer-copy w-100 mb-0 mt-2">{{ t('footer.copy') }}</p>
    </div>
  </footer>
</template>

<script>
import langMixin from '../langMixin.js'

export default {
  name: 'AppFooter',

  mixins: [langMixin],

  methods: {
    goHowItWorks() {
      if (this.$route.name === 'home') {
        // uz mājas lapas ritinām uz sadaļu
        const el = document.getElementById('how-it-works')
        if (el) el.scrollIntoView({ behavior: 'smooth' })
      } else {
        this.$router.push({ name: 'home' })
      }
    },
  },
}
</script>
