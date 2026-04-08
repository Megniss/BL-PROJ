<template>
  <div class="dashboard-page">

    <!-- Navigācija -->
    <AppNavbar />

    <div class="container-xl py-4 px-3 px-md-4">

      <div v-if="loading" class="text-center py-5 text-muted">
        <div class="fs-1">⏳</div>
        <p>{{ t('profile.loading') }}</p>
      </div>

      <template v-else>
        <!-- Profila karte -->
        <div class="card border mb-4">
          <div class="card-body d-flex flex-wrap align-items-center gap-4 p-4">
            <div class="profile-avatar">{{ initials }}</div>
            <div class="flex-grow-1">
              <h1 class="h4 fw-bold mb-1">{{ profile.name }}</h1>
              <p class="text-muted mb-1 small">{{ profile.email }}</p>
              <p class="text-muted mb-0 small">{{ t('profile.memberSince') }} {{ profile.joined }}</p>
            </div>
            <div class="d-flex gap-4">
              <div class="text-center">
                <div class="profile-stat-num">{{ profile.books }}</div>
                <div class="profile-stat-label">{{ t('profile.booksListed') }}</div>
              </div>
              <div class="text-center">
                <div class="profile-stat-num">{{ profile.swaps }}</div>
                <div class="profile-stat-label">{{ t('profile.swapsDone') }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Apmaiņas vēsture -->
        <h2 class="dash-section-title mb-3">{{ t('profile.swapHistory') }}</h2>

        <div v-if="history.length === 0" class="text-center py-5 text-muted">
          <div class="fs-1">🔄</div>
          <p class="fw-semibold mb-1" style="color:var(--ink)">{{ t('profile.noSwaps') }}</p>
          <p class="small mb-3">{{ t('profile.noSwapsSub') }}</p>
          <button class="btn btn-primary" @click="$router.push({ name: 'browse' })">{{ t('profile.browseBtn') }}</button>
        </div>

        <div v-else class="d-flex flex-column gap-2">
          <div v-for="swap in history" :key="swap.id" class="card border">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2 p-3">
              <div>
                <p class="mb-1 small">
                  <template v-if="swap.requester.id === authStore.user.id">
                    {{ t('profile.youGave') }} <strong>{{ swap.offered_book.title }}</strong> {{ t('profile.andReceived') }} <strong>{{ swap.wanted_book.title }}</strong>.
                  </template>
                  <template v-else>
                    {{ t('profile.youGave') }} <strong>{{ swap.wanted_book.title }}</strong> {{ t('profile.andReceived') }} <strong>{{ swap.offered_book.title }}</strong>.
                  </template>
                </p>
                <p class="text-muted mb-0" style="font-size:12px">{{ formatDate(swap.updated_at) }}</p>
              </div>
              <div class="d-flex align-items-center gap-2">
                <template v-if="hasRated(swap)">
                  <span style="color:#f5a623">
                    <span v-for="n in 5" :key="n">{{ n <= existingStars(swap) ? '★' : '☆' }}</span>
                  </span>
                </template>
                <button v-else class="btn btn-sm btn-outline-primary" @click="openRatingModal(swap)">
                  {{ t('profile.rate') }}
                </button>
                <span class="tag tag-green">{{ t('profile.completed') }}</span>
              </div>
            </div>
          </div>

          <div v-if="historyNextPage" class="text-center pt-2">
            <button class="btn btn-outline-secondary btn-sm" @click="loadMoreHistory" :disabled="historyLoading">
              {{ historyLoading ? t('profile.loadingMore') : t('profile.loadMore') }}
            </button>
          </div>
        </div>
      </template>

    </div>
    <AppFooter />
  </div>


  <RatingModal
    :open="ratingModal.open"
    :book="ratingModal.book"
    :sending="ratingModal.sending"
    :error="ratingModal.error"
    @close="ratingModal.open = false"
    @submit="submitRating"
  />
</template>

<script>
import axios from 'axios'
import { authStore } from '../authStore.js'
import langMixin from '../langMixin.js'
import AppNavbar from './AppNavbar.vue'
import AppFooter from './AppFooter.vue'
import RatingModal from './RatingModal.vue'

export default {
  name: 'Profile',

  components: { AppNavbar, AppFooter, RatingModal },

  mixins: [langMixin],

  data() {
    return {
      authStore,
      loading: true,
      profile: null,
      history: [],
      historyNextPage: null,
      historyLoading: false,
      ratingModal: {
        open: false,
        swap: null,
        book: null,
        sending: false,
        error: '',
      },
    }
  },

  computed: {
    initials() {
      if (!this.profile?.name) return '?'
      return this.profile.name
        .split(' ')
        .map(w => w[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
    }
  },

  mounted() {
    this.fetchAll()
  },

  methods: {
    async fetchAll() {
      this.loading = true
      try {
        const [profile, history] = await Promise.all([
          axios.get('/api/profile'),
          axios.get('/api/profile/history'),
        ])
        this.profile = profile.data
        this.history = history.data.data
        this.historyNextPage = history.data.next_page_url
      } finally {
        this.loading = false
      }
    },

    async loadMoreHistory() {
      if (! this.historyNextPage || this.historyLoading) return
      this.historyLoading = true
      try {
        const { data } = await axios.get(this.historyNextPage)
        this.history.push(...data.data)
        this.historyNextPage = data.next_page_url
      } finally {
        this.historyLoading = false
      }
    },

    formatDate(dateStr) {
      return new Date(dateStr).toLocaleDateString('en-GB', {
        day: 'numeric', month: 'short', year: 'numeric'
      })
    },

    receivedBook(swap) {
      if (swap.requester.id === this.authStore.user.id) {
        return swap.wanted_book
      }
      return swap.offered_book
    },

    hasRated(swap) {
      const book = this.receivedBook(swap)
      return swap.ratings?.some(r => r.book_id === book?.id && r.rater_id === this.authStore.user.id) ?? false
    },

    existingStars(swap) {
      const book = this.receivedBook(swap)
      const rating = swap.ratings?.find(r => r.book_id === book?.id && r.rater_id === this.authStore.user.id)
      return rating?.stars ?? null
    },

    openRatingModal(swap) {
      this.ratingModal.swap = swap
      this.ratingModal.book = this.receivedBook(swap)
      this.ratingModal.error = ''
      this.ratingModal.open = true
    },

    async submitRating({ stars, review }) {
      this.ratingModal.sending = true
      this.ratingModal.error = ''
      try {
        const { data } = await axios.post('/api/ratings', {
          swap_request_id: this.ratingModal.swap.id,
          stars: stars,
          review: review || null,
        })
        const swap = this.history.find(s => s.id === this.ratingModal.swap.id)
        if (swap) swap.ratings.push(data)
        this.ratingModal.open = false
      } catch (err) {
        this.ratingModal.error = err.response?.data?.message || 'Something went wrong.'
      } finally {
        this.ratingModal.sending = false
      }
    },
  }
}
</script>
