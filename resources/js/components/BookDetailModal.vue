<template>
  <div v-if="book" class="modal-overlay" @click.self="$emit('close')" role="dialog" aria-modal="true" :aria-labelledby="'detail-title-' + book.id">
    <div class="modal-card book-detail-card">

      <button class="detail-close-btn" @click="$emit('close')" :aria-label="t('bookDetail.close')">×</button>

      <div class="book-detail-layout">
        <div class="book-detail-cover-wrap" :style="!book.cover_image ? { background: coverColor(book) } : {}">
          <img v-if="book.cover_image" :src="'/storage/' + book.cover_image" :alt="book.title" class="book-detail-cover-img" />
          <span v-else class="book-card-genre">{{ t('genre.' + book.genre) }}</span>
        </div>

        <div class="book-detail-info">
          <h2 class="book-detail-title mb-1" :id="'detail-title-' + book.id">{{ book.title }}</h2>
          <p class="book-detail-author mb-3">{{ book.author }}</p>

          <div class="d-flex flex-wrap gap-1 mb-3">
            <span class="tag">{{ t('genre.' + book.genre) }}</span>
            <span class="tag">{{ t('lang.' + book.language) }}</span>
            <span class="tag">{{ book.condition }}</span>
            <span class="tag" :class="book.status === 'Available' ? 'tag-green' : 'tag-yellow'">
              {{ t('books.status.' + book.status) }}
            </span>
          </div>

          <div class="mb-3" style="font-size:0.9rem">
            <template v-if="book.ratings_count > 0">
              <span style="color:#f5a623;font-size:1.1rem">★</span>
              {{ Number(book.ratings_avg_stars).toFixed(1) }}
              <span class="text-muted ms-1">({{ book.ratings_count }})</span>
            </template>
            <span v-else class="detail-placeholder">{{ t('books.noRatings') }}</span>
          </div>

          <p v-if="book.description" class="book-detail-desc mb-3">{{ book.description }}</p>
          <p v-else class="detail-placeholder mb-3">{{ t('bookDetail.noDesc') }}</p>

          <p class="small mb-4">
            <span class="text-muted">{{ t('bookDetail.owner') }}:</span>
            <span class="owner-link ms-1" role="button" tabindex="0" @click="$emit('profile', book.user)" @keyup.enter="$emit('profile', book.user)">{{ book.user?.name }}</span>
          </p>

          <div class="detail-actions">
            <button v-if="!isOwnBook && !blocked" class="btn-swap" :disabled="book.status !== 'Available'" @click="$emit('swap', book)">
              <span class="btn-icon">⇄</span>{{ t('books.requestSwap') }}
            </button>
            <button v-if="!blocked && !isOwnBook" class="btn-msg-owner" @click="$emit('message', book.user)">
              <span class="btn-icon">✉</span>{{ t('books.msgOwner') }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script>
import langMixin from '../langMixin.js'
import { coverColor } from '../coverColor.js'
import { authStore } from '../authStore.js'

export default {
  name: 'BookDetailModal',
  mixins: [langMixin],
  props: {
    book: { type: Object, default: null },
    blocked: { type: Boolean, default: false },
  },
  emits: ['close', 'swap', 'message', 'profile'],
  data() {
    return { authStore }
  },
  computed: {
    isOwnBook() {
      return authStore.user && this.book?.user?.id === authStore.user.id
    }
  },
  methods: {
    coverColor,
  }
}
</script>
