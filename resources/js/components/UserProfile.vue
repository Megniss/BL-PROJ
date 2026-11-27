<template>
  <div class="profile-page">

    <AppNavbar>
      <template v-if="!authStore.user">
        <button class="btn btn-sm btn-outline-secondary" @click="$router.push({ name: 'login' })">{{ t('nav.login') }}</button>
        <button class="btn btn-sm btn-primary" @click="$router.push({ name: 'register' })">{{ t('nav.signup') }}</button>
      </template>
    </AppNavbar>

    <div class="page-body">
    <div v-if="loading" class="text-center py-5 text-muted">
      <div class="fs-1">⏳</div>
      <p>{{ t('profile.loading') }}</p>
    </div>

    <div v-else-if="error" class="text-center py-5 text-danger">{{ error }}</div>

    <template v-else-if="profile">
      <div class="container-xl py-4 px-3 px-md-4">
        <div class="card border mb-4">
          <div class="card-body d-flex flex-wrap align-items-start gap-4 p-4">
            <div class="up-avatar">{{ profile.name[0].toUpperCase() }}</div>
            <div class="flex-grow-1">
              <h1 class="h4 fw-bold mb-1">{{ profile.name }}</h1>
              <p v-if="profile.joined" class="text-muted mb-1 small">{{ t('profile.memberSince') }} {{ formatDate(profile.joined) }}</p>
              <p v-if="profile.distance_km !== null && profile.distance_km !== undefined" class="text-muted mb-3 small">
                📍 {{ t('location.fromYou').replace('{d}', formatDistance(profile.distance_km)) }}
              </p>
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
              <button v-if="!isBlocked && !theyBlockedMe" class="btn btn-primary" @click="goToMessages">{{ t('up.message') }} {{ profile.name }}</button>
              <button v-if="!theyBlockedMe" class="btn-block-user" :class="{ blocked: isBlocked }" @click="toggleBlock">
                {{ isBlocked ? t('up.unblock') : t('up.block') }}
              </button>
            </div>
          </div>
        </div>

        <h2 class="dash-section-title mb-3">{{ profile.name }} - {{ t('up.library') }}</h2>

        <div v-if="profile.library.length === 0" class="text-center py-5 text-muted">
          <div class="fs-1">📚</div>
          <p>{{ t('up.noBooks') }}</p>
        </div>

        <div v-else class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
          <div v-for="book in profile.library" :key="book.id" class="col">
            <div class="card h-100 book-card border" style="position:relative">
              <div v-if="book.status === 'Pending'" class="pending-overlay"></div>
              <div class="book-card-cover" :style="!book.cover_image ? { background: coverColor(book) } : {}">
                <img v-if="book.cover_image" :src="'/storage/' + book.cover_image" :alt="book.title" class="book-card-cover-img" />
                <span v-else class="book-card-genre">{{ book.genre }}</span>
              </div>
              <div class="card-body d-flex flex-column p-3">
                <h3 class="book-card-title mb-1">{{ book.title }}</h3>
                <p class="book-card-author mb-2">{{ book.author }}</p>
                <div class="d-flex flex-wrap gap-1 mb-2">
                  <span class="tag">{{ book.language }}</span>
                  <span class="tag" :class="conditionClass(book.condition)">{{ book.condition }}</span>
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
                <div v-if="authStore.user && authStore.user.id !== profile.id && !isBlocked && !theyBlockedMe" class="mt-auto pt-2">
                  <span v-if="book.i_have" class="copies-badge">✓ {{ t('copies.iHaveShort') }}</span>
                  <button v-else class="btn-swap" :disabled="book.status !== 'Available'" @click="requestSwap(book)">{{ t('books.requestSwap') }}</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ko šis lietotājs meklē - ja man tā ir, varu piedāvāt -->
        <template v-if="profile.wishlist && profile.wishlist.length">
          <h2 id="wishlist" class="dash-section-title mt-4 mb-1">♡ {{ t('up.wishTitle').replace('{name}', profile.name) }}</h2>
          <p class="dashboard-sub small mb-3">{{ t('up.wishSub') }}</p>
          <ul class="wishlist-list mt-0 mb-4" data-testid="profile-wishlist">
            <li v-for="w in profile.wishlist" :key="w.id" class="wishlist-item">
              <div class="wishlist-info">
                <span class="fw-semibold">{{ w.title }}</span>
                <span class="text-muted"> - {{ w.author }}</span>
              </div>
              <span v-if="w.offered" class="tag tag-green">✓ {{ t('up.offered') }}</span>
              <button
                v-else-if="w.my_book_id && canSwap"
                class="btn btn-sm btn-primary"
                @click="openOffer(w)"
                data-testid="offer-btn"
              >⇄ {{ t('up.offerMine') }}</button>
            </li>
          </ul>
        </template>

        <template v-if="profile.swap_history && profile.swap_history.length">
          <h2 class="dash-section-title mt-4 mb-3">{{ profile.name }} - {{ t('profile.swapHistory') }}</h2>
          <div class="d-flex flex-column gap-2 mb-4">
            <div v-for="(swap, i) in profile.swap_history" :key="i" class="card border">
              <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2 p-3">
                <p class="mb-0 small">
                  <strong>{{ profile.name }}</strong> {{ t('up.gave') }} <strong>{{ swap.gave }}</strong>
                  {{ t('profile.andReceived') }} <strong>{{ swap.received }}</strong>
                </p>
                <span class="text-muted" style="font-size:12px">{{ swap.date }}</span>
              </div>
            </div>
          </div>
        </template>

      </div>
    </template>

    </div> <!-- /page-body -->
    <AppFooter />
  </div>

  <SwapModal
    :open="swapModal.open"
    :wanted-book="swapModal.wantedBook"
    :my-books="swapModal.myBooks"
    :selected-book-id="swapModal.selectedBookId"
    :sending="swapModal.sending"
    :error="swapModal.error"
    :success="swapModal.success"
    @update:selected-book-id="swapModal.selectedBookId = $event"
    @close="closeSwapModal"
    @send="sendSwapRequest"
  />

  <!-- piedāvāju savu grāmatu, ko viņš meklē - izvēlos, ko gribu pretī -->
  <Teleport to="body">
    <div v-if="offer.wish" class="modal-overlay" @click.self="closeOffer" role="dialog" aria-modal="true" aria-labelledby="offer-title">
      <div class="modal-card">
        <div v-if="offer.success" class="text-center py-2">
          <div style="font-size:2.5rem">✅</div>
          <h2 class="modal-title mt-2 mb-1">{{ t('swap.sent') }}</h2>
          <p class="text-muted small mb-4">{{ t('swap.sentDesc') }}</p>
          <button class="btn btn-primary px-4" @click="closeOffer">{{ t('swap.sentClose') }}</button>
        </div>

        <template v-else>
          <h2 class="modal-title mb-2" id="offer-title">{{ t('up.offerTitle') }} ⇄</h2>
          <p class="modal-desc mb-3">
            {{ t('up.offerYouGive') }}: <strong>{{ offer.wish.title }}</strong> {{ t('up.by') }} {{ offer.wish.author }}
            <em>({{ t('up.offerTo').replace('{name}', profile.name) }})</em>
          </p>

          <template v-if="offerChoices.length">
            <p class="swap-hint">{{ t('up.offerPick').replace('{name}', profile.name) }}</p>
            <div class="swap-book-list mb-3">
              <div
                v-for="book in offerChoices"
                :key="book.id"
                class="swap-book-option"
                :class="{ selected: offer.wantedId === book.id }"
                role="button"
                tabindex="0"
                @click="offer.wantedId = book.id"
                @keyup.enter="offer.wantedId = book.id"
              >
                <div class="swap-book-info">
                  <strong>{{ book.title }}</strong>
                  <span>{{ book.author }}</span>
                </div>
                <span class="tag tag-green" v-if="offer.wantedId === book.id">✓ {{ t('up.selected') }}</span>
              </div>
            </div>
          </template>
          <p v-else class="swap-hint">{{ t('up.offerNothing').replace('{name}', profile.name) }}</p>

          <div v-if="offer.error" class="alert alert-danger py-2 px-3 mb-3" role="alert">{{ offer.error }}</div>
          <div class="d-flex justify-content-end gap-2">
            <button class="btn btn-outline-secondary" @click="closeOffer">{{ t('up.cancel') }}</button>
            <button v-if="!offerChoices.length" class="btn btn-primary" @click="goToMessages">{{ t('up.message') }} {{ profile.name }}</button>
            <button v-else class="btn btn-primary" :disabled="!offer.wantedId || offer.sending" @click="sendOffer">
              {{ offer.sending ? t('modal.saving') : t('up.offerSend') }}
            </button>
          </div>
        </template>
      </div>
    </div>
  </Teleport>
</template>

<script>
import axios from 'axios'
import { authStore } from '../authStore.js'
import SwapModal from './SwapModal.vue'
import AppNavbar from './AppNavbar.vue'
import AppFooter from './AppFooter.vue'
import { coverColor } from '../coverColor.js'
import { conditionClass } from '../conditionClass.js'
import langMixin from '../langMixin.js'
import { formatDistance } from '../distance.js'

export default {
  name: 'UserProfile',

  components: { SwapModal, AppNavbar, AppFooter },

  mixins: [langMixin],

  data() {
    return {
      authStore,
      profile: null,
      loading: true,
      error: '',
      isBlocked: false,
      theyBlockedMe: false,
      swapModal: {
        open: false,
        wantedBook: null,
        myBooks: [],
        selectedBookId: null,
        sending: false,
        error: '',
        success: false,
      },
      offer: { wish: null, wantedId: null, sending: false, error: '', success: false },
    }
  },

  computed: {
    // var mainīties - ielogojies, nav savs profils, nav bloķēts
    canSwap() {
      return !!authStore.user && authStore.user.id !== this.profile?.id && !this.isBlocked && !this.theyBlockedMe
    },

    // viņa grāmatas, ko varu paņemt pretī
    offerChoices() {
      return (this.profile?.library ?? []).filter(b => b.status === 'Available' && !b.i_have)
    },
  },

  async mounted() {
    await this.fetchProfile()
    // no My Library "Piedāvāt" - ?offer=manas grāmatas id
    const bookId = Number(this.$route.query.offer)
    const wish = bookId && this.profile?.wishlist?.find(w => w.my_book_id === bookId)
    if (wish && this.canSwap) {
      this.$nextTick(() => document.getElementById('wishlist')?.scrollIntoView({ block: 'center' }))
      this.openOffer(wish)
    }
  },

  methods: {
    formatDistance,

    async fetchProfile() {
      this.loading = true
      this.error = ''
      try {
        const { data } = await axios.get(`/api/users/${this.$route.params.id}`)
        this.profile = data
        this.isBlocked = data.is_blocked ?? false
        this.theyBlockedMe = data.they_blocked_me ?? false
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
        } catch { /* ignorē */ }
      }
    },

    closeSwapModal() {
      this.swapModal.open = false
      this.swapModal.success = false
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
        this.swapModal.success = true
      } catch (err) {
        this.swapModal.error = err.response?.data?.message || this.t('up.swapError')
      } finally {
        this.swapModal.sending = false
      }
    },

    openOffer(wish) {
      this.offer = { wish, wantedId: null, sending: false, error: '', success: false }
    },

    closeOffer() {
      this.offer = { wish: null, wantedId: null, sending: false, error: '', success: false }
      // lai pārlādējot logs neatveras vēlreiz
      if (this.$route.query.offer) this.$router.replace({ query: {} })
    },

    async sendOffer() {
      this.offer.error = ''
      this.offer.sending = true
      try {
        await axios.post('/api/swap-requests', {
          offered_book_id: this.offer.wish.my_book_id,
          wanted_book_id: this.offer.wantedId,
        })
        // abas grāmatas tagad apmaiņā
        this.profile.library = this.profile.library.filter(b => b.id !== this.offer.wantedId)
        this.offer.wish.offered = true
        this.offer.success = true
      } catch (err) {
        this.offer.error = err.response?.data?.message || this.t('up.swapError')
      } finally {
        this.offer.sending = false
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
      } catch { /* ignorē */ }
    },

    coverColor,
    conditionClass,

    formatDate(dateStr) {
      return new Date(dateStr).toLocaleDateString('en-GB', {
        year: 'numeric', month: 'long',
      })
    },
  },
}
</script>
