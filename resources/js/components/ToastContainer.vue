<template>
  <Teleport to="body">
    <div class="toast-wrap" aria-live="polite">
      <transition-group name="toast-slide" tag="div">
        <div v-for="toast in toasts" :key="toast.id" class="toast-item" :class="'toast-' + toast.type">
          {{ toast.message }}
        </div>
      </transition-group>
    </div>
  </Teleport>
</template>

<script>
import { toasts } from '../toast.js'

export default {
  name: 'ToastContainer',
  data() {
    return { toasts }
  }
}
</script>
