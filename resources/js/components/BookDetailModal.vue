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
          </div>

          <div class="mb-1" style="font-size:0.9rem">
            <template v-if="book.ratings_count > 0">
              <span style="color:#f5a623;font-size:1.1rem">★</span>
              {{ Number(book.ratings_avg_stars).toFixed(1) }}
              <span class="text-muted ms-1">({{ book.ratings_count }})</span>
            </template>
            <span v-else class="detail-placeholder">{{ t('books.noRatings') }}</span>
          </div>
        </div>
      </div>

      <!-- visi lietotāji, kuriem ir šī grāmata -->
      <div class="book-copies">
        <p class="suggestions-label">{{ t('copies.title') }}</p>

        <p v-if="loadingCopies" class="detail-placeholder">{{ t('dash.loading') }}</p>
        <p v-else-if="iHave" class="detail-placeholder">{{ t('copies.iHave') }}</p>
        <p v-else-if="copies.length === 0" class="detail-placeholder">{{ t('copies.none') }}</p>

        <div v-for="c in copies" :key="c.id" class="copy-row">
          <div class="copy-cover" :style="!c.cover_image ? { background: coverColor(c) } : {}">
            <img v-if="c.cover_image" :src="'/storage/' + c.cover_image" :alt="c.title" />
          </div>

          <div class="copy-info">
            <p class="mb-1">
              <span class="owner-link fw-semibold" role="button" tabindex="0" @click="$emit('profile', c.user)" @keyup.enter="$emit('profile', c.user)">{{ c.user?.name }}</span>
              <span v-if="c.distance_km !== null && c.distance_km !== undefined" class="book-card-distance ms-1">· 📍 {{ formatDistance(c.distance_km) }}</span>
            </p>
            <div class="d-flex flex-wrap gap-1 mb-1">
              <span class="tag">{{ t('lang.' + c.language) }}</span>
              <span class="tag" :class="conditionClass(c.condition)">{{ c.condition }}</span>
              <span v-if="c.ratings_count > 0" class="tag">★ {{ Number(c.ratings_avg_stars).toFixed(1) }} ({{ c.ratings_count }})</span>
            </div>
            <p v-if="c.description" class="copy-desc mb-0">{{ c.description }}</p>
          </div>

          <div v-if="!c.blocked" class="copy-actions">
            <button class="btn-swap btn-swap-sm" @click="$emit('swap', c)">⇄ {{ t('books.requestSwap') }}</button>
            <button class="btn-msg-owner btn-sm" @click="$emit('message', c.user)">✉ {{ t('books.msgOwner') }}</button>
          </div>
        </div>
      </div>

      <!-- ieteikumi -->
      <div v-if="suggestions.length" class="book-suggestions">
        <p class="suggestions-label">{{ t('bookDetail.suggestions') }}</p>
        <div class="suggestions-scroll">
          <div
            v-for="s in suggestions"
            :key="s.id"
            class="suggestion-card"
            role="button"
            tabindex="0"
            @click="$emit('suggest', s)"
            @keyup.enter="$emit('suggest', s)"
          >
            <div class="suggestion-cover" :style="!s.cover_image ? { background: coverColor(s) } : {}">
              <img v-if="s.cover_image" :src="'/storage/' + s.cover_image" :alt="s.title" />
              <span v-else class="suggestion-genre-text">{{ t('genre.' + s.genre) }}</span>
            </div>
            <div class="suggestion-info">
              <p class="suggestion-title">{{ s.title }}</p>
              <p class="suggestion-author">{{ s.author }}</p>
              <span class="tag" style="font-size:0.65rem">{{ t('genre.' + s.genre) }}</span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script>
import axios from 'axios'
import langMixin from '../langMixin.js'
import { coverColor } from '../coverColor.js'
import { conditionClass } from '../conditionClass.js'
import { formatDistance } from '../distance.js'

export default {
  name: 'BookDetailModal',
  mixins: [langMixin],
  props: {
    book: { type: Object, default: null },
  },
  emits: ['close', 'swap', 'message', 'profile', 'suggest'],
  data() {
    return { suggestions: [], copies: [], iHave: false, loadingCopies: false }
  },
  watch: {
    book(val) {
      this.suggestions = []
      this.copies = []
      this.iHave = false
      if (val) {
        this.fetchCopies(val)
        this.fetchSuggestions(val.id)
      }
    }
  },
  methods: {
    coverColor,
    conditionClass,
    formatDistance,

    async fetchCopies(book) {
      this.loadingCopies = true
      try {
        const { data } = await axios.get('/api/browse/copies', { params: { title: book.title, author: book.author } })
        this.copies = data.copies
        this.iHave = data.i_have
      } catch { /* klusums */ } finally {
        this.loadingCopies = false
      }
    },

    async fetchSuggestions(bookId) {
      try {
        const { data } = await axios.get(`/api/books/${bookId}/suggestions`)
        this.suggestions = data
      } catch { /* klusums */ }
    },
  }
}
</script>
