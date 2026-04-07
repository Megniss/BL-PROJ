<template>
  <div class="profile-page">

    <!-- Navigācija -->
    <AppNavbar>
      <template v-if="!authStore.user">
        <button class="btn btn-sm btn-outline-secondary" @click="$router.push({ name: 'login' })">{{ t('nav.login') }}</button>
        <button class="btn btn-sm btn-primary" @click="$router.push({ name: 'register' })">{{ t('nav.signup') }}</button>
      </template>
    </AppNavbar>

    <div v-if="loading" class="text-center py-5 text-muted">
      <div class="fs-1">⏳</div>
      <p>{{ t('profile.loading') }}</p>
    </div>

    <div v-else-if="error" class="text-center py-5 text-danger">{{ error }}</div>

    <template v-else-if="profile">
      <div class="container-xl py-4 px-3 px-md-4">
        <!-- Profila karte -->
        <div class="card border mb-4">
          <div class="card-body d-flex flex-wrap align-items-start gap-4 p-4">
            <div class="up-avatar">{{ profile.name[0].toUpperCase() }}</div>
            <div class="flex-grow-1">
              <h1 class="h4 fw-bold mb-1">{{ profile.name }}</h1>
              <p v-if="profile.joined" class="text-muted mb-3 small">{{ t('profile.memberSince') }} {{ formatDate(profile.joined) }}</p>
              <div class="d-flex gap-4">
                <div>
                  <div class="up-stat-num">{{ profile.books }}</div>
                  <div class="up-stat-label">{{ t('profile.booksListed') }}</div>
                </div>
                <div v-if="profile.swaps !== null">
                  <div class="up-stat-num">{{ profile.swaps }}</div>
                  <div class="up-stat-label">{{ t('profile.swapsDone') }}</div>
                </div>
              </div>
            </div>
            <div v-if="authStore.user && authStore.user.id !== profile.id" class="d-flex flex-column gap-2 align-items-end">
              <button class="btn btn-primary" @click="goToMessages">{{ t('up.message') }} {{ profile.name }}</button>
              <button class="btn-block-user" :class="{ blocked: isBlocked }" @click="toggleBlock">
                {{ isBlocked ? t('up.unblock') : t('up.block') }}
              </button>
            </div>
          </div>
        </div>

        <!-- Bibliotēkas sadaļa -->
        <h2 class="dash-section-title mb-3">{{ profile.name }} — {{ t('up.library') }}</h2>

        <div v-if="profile.library.length === 0" class="text-center py-5 text-muted">
          <div class="fs-1">📚</div>
          <p>{{ t('up.noBooks') }}</p>
        </div>

        <div v-else class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
          <div v-for="book in profile.library" :key="book.id" class="col">
            <div class="card h-100 book-card border">
              <div class="book-card-cover" :style="!book.cover_image ? { background: coverColor(book) } : {}">
                <img v-if="book.cover_image" :src="'/storage/' + book.cover_image" :alt="book.title" class="book-card-cover-img" />
                <span v-else class="book-card-genre">{{ book.genre }}</span>
              </div>
              <div class="card-body d-flex flex-column p-3">
                <h3 class="book-card-title mb-1">{{ book.title }}</h3>
                <p class="book-card-author mb-2">{{ book.author }}</p>
                <div class="d-flex flex-wrap gap-1 mb-2">
                  <span class="tag">{{ book.language }}</span>
                  <span class="tag">{{ book.condition }}</span>
                  <span class="tag" :class="book.status === 'Available' ? 'tag-green' : 'tag-yellow'">{{ book.status }}</span>
                </div>
                <div class="mb-2" style="font-size:0.8rem; color:#888">
                  <template v-if="book.ratings_count > 0">
                    <span style="color:#f5a623">★</span>
                    {{ Number(book.ratings_avg_stars).toFixed(1) }}
                    <span class="text-muted">({{ book.ratings_count }})</span>
                  </template>
                  <span v-else class="text-muted">{{ t('books.noRatings') }}</span>
                </div>
                <p v-if="book.description" class="book-card-desc mb-0">{{ book.description }}</p>
                <div v-if="authStore.user && authStore.user.id !== profile.id" class="mt-auto pt-2">
                  <button class="btn-swap" :disabled="book.status !== 'Available'" @click="requestSwap(book)">{{ t('books.requestSwap') }}</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

  </div>

  <SwapModal
    :open="swapModal.open"
    :wanted-book="swapModal.wantedBook"
    :my-books="swapModal.myBooks"
    :selected-book-id="swapModal.selectedBookId"
    :sending="swapModal.sending"
    :error="swapModal.error"
    @update:selected-book-id="swapModal.selectedBookId = $event"
    @close="closeSwapModal"
    @send="sendSwapRequest"
  />
</template>

<script>
import axios from 'axios'
import { authStore } from '../authStore.js'
import SwapModal from './SwapModal.vue'
import AppNavbar from './AppNavbar.vue'
import { coverColor } from '../coverColor.js'
import langMixin from '../langMixin.js'

export default {
  name: 'UserProfile',

  components: { SwapModal, AppNavbar },

  mixins: [langMixin],

  data() {
    return {
      authStore,
      profile:   null,
      loading:   true,
      error:     '',
      isBlocked: false,
      swapModal: {
        open: false,
        wantedBook: null,
        myBooks: [],
        selectedBookId: null,
        sending: false,
        error: '',
      },
    }
  },

  mounted() {
    this.fetchProfile()
  },

  methods: {
    async fetchProfile() {
      this.loading = true
      this.error   = ''
      try {
        const { data } = await axios.get(`/api/users/${this.$route.params.id}`)
        this.profile   = data
        this.isBlocked = data.is_blocked ?? false
      } catch (err) {
        this.error = err.response?.status === 404
          ? this.t('up.notFound')
          : this.t('up.loadError')
      } finally {
        this.loading = false
      }
    },

    goToMessages() {
      this.$router.push({
        name:  'messages',
        query: { userId: this.profile.id, userName: this.profile.name },
      })
    },

    async requestSwap(book) {
      this.swapModal.wantedBook = book
      this.swapModal.selectedBookId = null
      this.swapModal.error = ''
      this.swapModal.myBooks = []
      this.swapModal.open = true

      if (authStore.user) {
        try {
          const { data } = await axios.get('/api/books')
          this.swapModal.myBooks = data.filter(b => b.status === 'Available')
        } catch { /* ignore */ }
      }
    },

    closeSwapModal() {
      this.swapModal.open = false
    },

    async sendSwapRequest() {
      this.swapModal.error = ''
      this.swapModal.sending = true
      try {
        await axios.post('/api/swap-requests', {
          offered_book_id: this.swapModal.selectedBookId,
          wanted_book_id:  this.swapModal.wantedBook.id,
        })
        this.profile.library = this.profile.library.filter(b => b.id !== this.swapModal.wantedBook.id)
        this.closeSwapModal()
      } catch (err) {
        this.swapModal.error = err.response?.data?.message || this.t('up.swapError')
      } finally {
        this.swapModal.sending = false
      }
    },

    async toggleBlock() {
      try {
        if (this.isBlocked) {
          await axios.delete(`/api/blocks/${this.profile.id}`)
          this.isBlocked = false
        } else {
          await axios.post(`/api/blocks/${this.profile.id}`)
          this.isBlocked = true
        }
      } catch { /* ignore */ }
    },

    coverColor,

    formatDate(dateStr) {
      return new Date(dateStr).toLocaleDateString('en-GB', {
        year: 'numeric', month: 'long',
      })
    },
  },
}
</script>
