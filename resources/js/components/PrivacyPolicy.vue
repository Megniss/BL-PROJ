<template>
  <div class="bookloop-home">

    <AppNavbar>
      <button class="about-back-btn" @click="$router.push({ name: 'home' })">← {{ t('about.back') }}</button>
    </AppNavbar>

    <section class="about-hero py-5">
      <div class="about-hero-bg-mark" aria-hidden="true">⇄</div>
      <div class="container-xl text-center py-3" style="position:relative; z-index:1">
        <h1 class="about-title mb-3">{{ t('privacy.title') }}</h1>
        <p class="about-subtitle">{{ t('privacy.subtitle') }}</p>
      </div>
    </section>

    <div class="container" style="max-width:760px; padding: 48px 24px;">
      <div class="d-flex flex-column gap-4">

        <div class="about-card">
          <div class="about-card-accent" style="background:var(--green)"></div>
          <div class="about-card-icon">🔒</div>
          <div>
            <h2 class="about-card-title mb-2">{{ t('privacy.deletion.title') }}</h2>
            <p class="about-card-text mb-0">{{ t('privacy.deletion.body') }}</p>
          </div>
        </div>

        <div class="about-card">
          <div class="about-card-accent" style="background:#e07b39"></div>
          <div class="about-card-icon">🚫</div>
          <div>
            <h2 class="about-card-title mb-2">{{ t('privacy.blocking.title') }}</h2>
            <p class="about-card-text mb-0">{{ t('privacy.blocking.body') }}</p>
          </div>
        </div>

        <div class="about-card">
          <div class="about-card-accent" style="background:#4f5d7c"></div>
          <div class="about-card-icon">📬</div>
          <div>
            <h2 class="about-card-title mb-2">{{ t('privacy.contact.title') }}</h2>
            <p class="about-card-text mb-0">{{ t('privacy.contact.body') }}</p>
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
  name: 'PrivacyPolicy',
  components: { AppNavbar, AppFooter },
  mixins: [langMixin],
}
</script>
