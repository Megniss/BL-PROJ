<template>
  <div class="bookloop-home">

    <AppNavbar>
      <button class="about-back-btn" @click="$router.push({ name: 'home' })">← {{ t('about.back') }}</button>
    </AppNavbar>

    <section class="about-hero py-5">
      <div class="about-hero-bg-mark" aria-hidden="true">⇄</div>
      <div class="container-xl text-center py-3" style="position:relative; z-index:1">
        <span class="hero-badge mb-3 d-inline-block">{{ t('about.badge') }}</span>
        <h1 class="about-title mb-3">{{ t('about.title') }}</h1>
        <p class="about-subtitle">{{ t('about.subtitle') }}</p>
      </div>
    </section>

    <div class="container" style="max-width:760px; padding: 48px 24px;">
      <div class="d-flex flex-column gap-4">

        <div class="about-card" tabindex="0">
          <div class="about-card-accent" style="background:var(--green)"></div>
          <div class="about-card-icon">📚</div>
          <div>
            <h2 class="about-card-title mb-2">{{ t('about.what.title') }}</h2>
            <p class="about-card-text mb-0">{{ t('about.what.body') }}</p>
          </div>
        </div>

        <div class="about-card" tabindex="0">
          <div class="about-card-accent" style="background:var(--amber)"></div>
          <div class="about-card-icon">💡</div>
          <div>
            <h2 class="about-card-title mb-2">{{ t('about.why.title') }}</h2>
            <p class="about-card-text mb-0">{{ t('about.why.body') }}</p>
          </div>
        </div>

        <div class="about-card about-card--creator" tabindex="0">
          <div class="about-card-accent" style="background:#4f5d7c"></div>
          <div class="about-card-icon">👨‍💻</div>
          <div>
            <h2 class="about-card-title mb-2">{{ t('about.creator.title') }}</h2>
            <p class="about-card-text mb-3">{{ t('about.creator.body') }}</p>
            <div class="d-flex flex-wrap gap-2">
              <span class="about-tag about-tag--laravel">Laravel</span>
              <span class="about-tag about-tag--vue">Vue.js</span>
              <span class="about-tag about-tag--sqlite">SQLite</span>
              <span class="about-tag about-tag--vite">Vite</span>
            </div>
          </div>
        </div>

      </div>
    </div>

    <AppFooter />

  </div>
</template>

<script>
import langMixin from '../langMixin.js'
import AppNavbar from './AppNavbar.vue'
import AppFooter from './AppFooter.vue'

export default {
  name: 'About',
  components: { AppNavbar, AppFooter },
  mixins: [langMixin],
}
</script>
