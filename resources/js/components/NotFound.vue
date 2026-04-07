<template>
  <div class="bookloop-home">
    <AppNavbar />

    <div class="text-center py-5 mt-5">
      <div class="brand-icon" style="font-size:5rem; line-height:1">⇄</div>
      <h1 class="hero-title mt-3 mb-2">404</h1>
      <p class="hero-subtitle mb-4">This page doesn't exist.</p>
      <button class="btn btn-primary px-5" @click="$router.push({ name: 'home' })">Back to Home</button>
    </div>
  </div>
</template>

<script>
import AppNavbar from './AppNavbar.vue'

export default {
  name: 'NotFound',
  components: { AppNavbar },
}
</script>
