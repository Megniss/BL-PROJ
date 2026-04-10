<template>
  <Teleport to="body">
    <div v-if="open" class="modal-overlay" @click.self="$emit('close')" role="dialog" aria-modal="true" aria-labelledby="swap-modal-title">
      <div class="modal-card">

        <!-- veiksmīgi nosūtīts -->
        <div v-if="success" class="text-center py-2">
          <div style="font-size:2.5rem">✅</div>
          <h2 class="modal-title mt-2 mb-1">{{ t('swap.sent') }}</h2>
          <p class="text-muted small mb-4">{{ t('swap.sentDesc') }}</p>
          <button class="btn btn-primary px-4" @click="$emit('close')">{{ t('swap.sentClose') }}</button>
        </div>

        <template v-else>
          <h2 class="modal-title mb-2" id="swap-modal-title">{{ t('books.requestSwap') }} ⇄</h2>
          <p class="modal-desc mb-3">
            {{ t('up.wantBook') }}: <strong>{{ wantedBook?.title }}</strong>
            {{ t('up.by') }} {{ wantedBook?.author }}
            <template v-if="wantedBook?.user?.name">
              <em>({{ t('up.ownedBy') }} {{ wantedBook.user.name }})</em>
            </template>
          </p>

          <div v-if="!authStore.user">
            <p class="swap-hint">{{ t('up.loginToSwap') }}</p>
            <div class="d-flex justify-content-end gap-2 mt-3">
              <button class="btn btn-outline-secondary" @click="$emit('close')">{{ t('up.cancel') }}</button>
              <button class="btn btn-primary" @click="$router.push({ name: 'login' })">{{ t('nav.login') }}</button>
            </div>
          </div>

          <div v-else-if="myBooks.length === 0">
            <p class="swap-hint">{{ t('up.needBook') }}</p>
            <div class="d-flex justify-content-end gap-2 mt-3">
              <button class="btn btn-outline-secondary" @click="$emit('close')">{{ t('up.cancel') }}</button>
              <button class="btn btn-primary" @click="$router.push({ name: 'dashboard' })">{{ t('dash.addFirst') }}</button>
            </div>
          </div>

          <div v-else>
            <p class="swap-hint">{{ t('up.pickBook') }}</p>
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
                <span class="tag tag-green" v-if="selectedBookId === book.id">✓ {{ t('up.selected') }}</span>
              </div>
            </div>
            <div v-if="error" class="alert alert-danger py-2 px-3 mb-3" role="alert">{{ error }}</div>
            <div class="d-flex justify-content-end gap-2">
              <button class="btn btn-outline-secondary" @click="$emit('close')">{{ t('up.cancel') }}</button>
              <button class="btn btn-primary" :disabled="!selectedBookId || sending" @click="$emit('send')">
                {{ sending ? t('up.sending') : t('up.sendRequest') }}
              </button>
            </div>
          </div>
        </template>

      </div>
    </div>
  </Teleport>
</template>

<script>
import { authStore } from '../authStore.js'
import langMixin from '../langMixin.js'

export default {
  name: 'SwapModal',

  mixins: [langMixin],

  props: {
    open: { type: Boolean, required: true },
    wantedBook: { type: Object, default: null },
    myBooks: { type: Array, default: () => [] },
    selectedBookId: { type: Number, default: null },
    sending: { type: Boolean, default: false },
    error: { type: String, default: '' },
    success: { type: Boolean, default: false },
  },

  emits: ['close', 'send', 'update:selectedBookId'],

  data() {
    return { authStore }
  }
}
</script>
