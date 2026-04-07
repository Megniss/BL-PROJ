
<template>
  <div>
    <div class="scroll-progress" :style="{ width: scrollProgress + '%' }"></div>
    <router-view></router-view>
    <button v-if="showBackToTop" class="back-to-top" @click="scrollToTop" aria-label="Atgriezties uz augšu">↑</button>
  </div>
</template>

<script>
export default {
  name: 'App',

  data() {
    return {
      scrollProgress: 0,
      showBackToTop: false,
    }
  },

  mounted() {
    window.addEventListener('scroll', this.onScroll, { passive: true })
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
  }
}
</script>
