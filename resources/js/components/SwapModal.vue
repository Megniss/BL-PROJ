<template>
  <Teleport to="body">
    <div v-if="open" class="modal-overlay" @click.self="$emit('close')" role="dialog" aria-modal="true" aria-labelledby="swap-modal-title">
      <div class="modal-card">
        <h2 class="modal-title mb-2" id="swap-modal-title">Request a Swap ⇄</h2>
        <p class="modal-desc mb-3">
          You want: <strong>{{ wantedBook?.title }}</strong>
          by {{ wantedBook?.author }}
          <template v-if="wantedBook?.user?.name">
            <em>(owned by {{ wantedBook.user.name }})</em>
          </template>
        </p>

        <div v-if="!authStore.user">
          <p class="swap-hint">You need to be logged in to request a swap.</p>
          <div class="d-flex justify-content-end gap-2 mt-3">
            <button class="btn btn-outline-secondary" @click="$emit('close')">Cancel</button>
            <button class="btn btn-primary" @click="$router.push({ name: 'login' })">Log In</button>
          </div>
        </div>

        <div v-else-if="myBooks.length === 0">
          <p class="swap-hint">You need at least one <strong>Available</strong> book in your library to offer in exchange.</p>
          <div class="d-flex justify-content-end gap-2 mt-3">
            <button class="btn btn-outline-secondary" @click="$emit('close')">Cancel</button>
            <button class="btn btn-primary" @click="$router.push({ name: 'dashboard' })">Add a Book</button>
          </div>
        </div>

        <div v-else>
          <p class="swap-hint">Pick one of your books to offer in exchange:</p>
          <div class="swap-book-list mb-3">
            <div
              v-for="book in myBooks"
              :key="book.id"
              class="swap-book-option"
              :class="{ selected: selectedBookId === book.id }"
              @click="$emit('update:selectedBookId', book.id)"
            >
              <div class="swap-book-info">
                <strong>{{ book.title }}</strong>
                <span>{{ book.author }}</span>
              </div>
              <span class="tag tag-green" v-if="selectedBookId === book.id">✓ Selected</span>
            </div>
          </div>
          <div v-if="error" class="alert alert-danger py-2 px-3 mb-3" role="alert">{{ error }}</div>
          <div class="d-flex justify-content-end gap-2">
            <button class="btn btn-outline-secondary" @click="$emit('close')">Cancel</button>
            <button class="btn btn-primary" :disabled="!selectedBookId || sending" @click="$emit('send')">
              {{ sending ? 'Sending…' : 'Send Request' }}
            </button>
          </div>
        </div>

      </div>
    </div>
  </Teleport>
</template>

<script>
import { authStore } from '../authStore.js'

export default {
  name: 'SwapModal',

  props: {
    open: { type: Boolean, required: true },
    wantedBook: { type: Object, default: null },
    myBooks: { type: Array, default: () => [] },
    selectedBookId: { type: Number, default: null },
    sending: { type: Boolean, default: false },
    error: { type: String, default: '' }
  },

  emits: ['close', 'send', 'update:selectedBookId'],

  data() {
    return { authStore }
  }
}
</script>
