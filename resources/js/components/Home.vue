<template>
  <div class="bookloop-home">

    <!-- Navigācija -->
    <AppNavbar>
      <button class="btn btn-sm btn-link text-secondary text-decoration-none" @click="$router.push({ name: 'browse' })">{{ t('nav.browse') }}</button>
      <button class="btn btn-sm btn-link text-secondary text-decoration-none" @click="scrollToHowItWorks">{{ t('nav.howItWorks') }}</button>
      <template v-if="!authStore.user">
        <button class="btn btn-sm btn-outline-secondary" @click="goToLogin">{{ t('nav.login') }}</button>
        <button class="btn btn-sm btn-primary" @click="goToRegister">{{ t('nav.signup') }}</button>
      </template>
    </AppNavbar>

    <!-- Galvenais bloks -->
    <section class="hero-section py-5">
      <div class="container-xl py-3 d-flex align-items-center gap-5">

        <!-- teksts -->
        <div class="flex-grow-1">
          <h1 class="hero-title mb-3">
            {{ t('hero.title1') }}<br>
            <span class="accent">{{ t('hero.title2') }}</span>
          </h1>
          <p class="hero-subtitle mb-4">{{ t('hero.subtitle') }}</p>

          <div v-if="stats" class="hero-stats mb-4">
            <span class="hero-stat">📚 <strong>{{ stats.books }}</strong> {{ t('hero.statBooks') }}</span>
            <span class="hero-stat-divider">·</span>
            <span class="hero-stat">👥 <strong>{{ stats.users }}</strong> {{ t('hero.statUsers') }}</span>
          </div>

          <button v-if="!authStore.user" class="btn btn-primary btn-lg px-5" @click="goToRegister">
            {{ t('cta.btn') }}
          </button>
        </div>

        <!-- grāmatas animācija -->
        <div class="hero-book-wrap d-none d-lg-block">
          <div class="hero-book-float">
          <div class="hero-book-open">
            <div class="hb-left">
              <div class="hb-line"></div>
              <div class="hb-line short"></div>
              <div class="hb-line"></div>
              <div class="hb-line short"></div>
              <div class="hb-line"></div>
              <div class="hb-line short"></div>
              <div class="hb-line"></div>
            </div>
            <div class="hb-spine"></div>
            <div class="hb-right">
              <div class="hb-logo">⇄</div>
              <div class="hb-logo-text">BookLoop</div>
            </div>
          </div>
          </div>
          <div class="hb-shadow"></div>
        </div>

      </div>
    </section>

    <!-- Tikko pievienotās -->
    <section class="container-xl py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0">{{ t('featured.recent') }}</h2>
        <button class="btn btn-sm btn-outline-secondary view-all-btn" @click="$router.push({ name: 'browse', query: { sort: 'newest' } })">
          <span class="d-none d-sm-inline">{{ t('featured.viewAll') }} </span>→
        </button>
      </div>
      <div class="featured-scroll-wrap"><div class="featured-scroll">
        <template v-if="booksLoading">
          <div v-for="i in 6" :key="i" class="featured-card skel-card">
            <div class="skel-cover"></div>
            <div class="featured-body">
              <div class="skel-line" style="width:72%"></div>
              <div class="skel-line mt-2" style="width:41%"></div>
            </div>
          </div>
        </template>
        <template v-else>
          <div v-for="book in recentBooks" :key="book.id" class="featured-card" @click="openDetail(book)">
            <div v-if="book.status === 'Pending'" class="pending-overlay"></div>
            <div class="featured-cover" :style="!book.cover_image ? { background: coverColor(book) } : {}">
              <img v-if="book.cover_image" :src="'/storage/' + book.cover_image" :alt="book.title" class="book-card-cover-img" />
              <span class="book-card-genre">{{ t('genre.' + book.genre) }}</span>
              <button v-if="book.status === 'Available'" class="card-quick-swap" @click.stop="authStore.user ? requestSwap(book) : $router.push({ name: 'login' })">⇄ {{ t('featured.swap') }}</button>
            </div>
            <div class="featured-body">
              <p class="featured-title">{{ book.title }}</p>
              <p class="featured-author">{{ book.author }}</p>
              <div class="featured-rating book-card-rating">
                <template v-if="book.ratings_count > 0">
                  <span v-for="s in 5" :key="s" :class="s <= Math.round(book.ratings_avg_stars) ? 'star filled' : 'star'">★</span>
                  <span class="rating-count">({{ book.ratings_count }})</span>
                </template>
                <span v-else class="rating-none">{{ t('featured.noRatings') }}</span>
              </div>
            </div>
          </div>
        </template>
      </div></div>
    </section>

    <!-- Populārākās grāmatas -->
    <section class="container-xl py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0">{{ t('featured.popular') }}</h2>
        <button class="btn btn-sm btn-outline-secondary view-all-btn" @click="$router.push({ name: 'browse', query: { sort: 'popular' } })">
          <span class="d-none d-sm-inline">{{ t('featured.viewAll') }} </span>→
        </button>
      </div>
      <div class="featured-scroll-wrap"><div class="featured-scroll">
        <template v-if="booksLoading">
          <div v-for="i in 6" :key="i" class="featured-card skel-card">
            <div class="skel-cover"></div>
            <div class="featured-body">
              <div class="skel-line" style="width:68%"></div>
              <div class="skel-line mt-2" style="width:44%"></div>
            </div>
          </div>
        </template>
        <template v-else>
          <div v-for="book in popularBooks" :key="book.id" class="featured-card" @click="openDetail(book)">
            <div v-if="book.status === 'Pending'" class="pending-overlay"></div>
            <div class="featured-cover" :style="!book.cover_image ? { background: coverColor(book) } : {}">
              <img v-if="book.cover_image" :src="'/storage/' + book.cover_image" :alt="book.title" class="book-card-cover-img" />
              <span class="book-card-genre">{{ t('genre.' + book.genre) }}</span>
              <button v-if="book.status === 'Available'" class="card-quick-swap" @click.stop="authStore.user ? requestSwap(book) : $router.push({ name: 'login' })">⇄ {{ t('featured.swap') }}</button>
            </div>
            <div class="featured-body">
              <p class="featured-title">{{ book.title }}</p>
              <p class="featured-author">{{ book.author }}</p>
              <div class="featured-rating book-card-rating">
                <template v-if="book.ratings_count > 0">
                  <span v-for="s in 5" :key="s" :class="s <= Math.round(book.ratings_avg_stars) ? 'star filled' : 'star'">★</span>
                  <span class="rating-count">({{ book.ratings_count }})</span>
                </template>
                <span v-else class="rating-none">{{ t('featured.noRatings') }}</span>
              </div>
            </div>
          </div>
        </template>
      </div></div>
    </section>

    <!-- Augstāk vērtētās -->
    <section v-if="booksLoading || topRatedBooks.length" class="container-xl py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0">{{ t('featured.topRated') }}</h2>
        <button class="btn btn-sm btn-outline-secondary view-all-btn" @click="$router.push({ name: 'browse', query: { sort: 'top_rated' } })">
          <span class="d-none d-sm-inline">{{ t('featured.viewAll') }} </span>→
        </button>
      </div>
      <div class="featured-scroll-wrap"><div class="featured-scroll">
        <template v-if="booksLoading">
          <div v-for="i in 6" :key="i" class="featured-card skel-card">
            <div class="skel-cover"></div>
            <div class="featured-body">
              <div class="skel-line" style="width:78%"></div>
              <div class="skel-line mt-2" style="width:47%"></div>
            </div>
          </div>
        </template>
        <template v-else>
          <div v-for="book in topRatedBooks" :key="book.id" class="featured-card" @click="openDetail(book)">
            <div v-if="book.status === 'Pending'" class="pending-overlay"></div>
            <div class="featured-cover" :style="!book.cover_image ? { background: coverColor(book) } : {}">
              <img v-if="book.cover_image" :src="'/storage/' + book.cover_image" :alt="book.title" class="book-card-cover-img" />
              <span class="book-card-genre">{{ t('genre.' + book.genre) }}</span>
              <button v-if="book.status === 'Available'" class="card-quick-swap" @click.stop="authStore.user ? requestSwap(book) : $router.push({ name: 'login' })">⇄ {{ t('featured.swap') }}</button>
            </div>
            <div class="featured-body">
              <p class="featured-title">{{ book.title }}</p>
              <p class="featured-author">{{ book.author }}</p>
              <div class="featured-rating book-card-rating">
                <span v-for="s in 5" :key="s" :class="s <= Math.round(book.ratings_avg_stars) ? 'star filled' : 'star'">★</span>
                <span class="rating-count">({{ book.ratings_count }})</span>
              </div>
            </div>
          </div>
        </template>
      </div></div>
    </section>

    <!-- Meklēšanas josla — apakšā -->
    <section class="border-top py-5 scroll-reveal" id="books">
      <div class="container-xl text-center">
        <h2 class="section-title mb-2">{{ t('search.title') }}</h2>
        <p class="text-muted mb-4">{{ t('search.subtitle') }}</p>
        <div class="home-search-bar mx-auto">
          <input v-model="homeSearch" class="form-control" :placeholder="t('search.placeholder')" @keyup.enter="browseTo" />
          <button class="btn btn-primary px-4" @click="browseTo">{{ t('search.btn') }}</button>
        </div>
        <button class="btn btn-outline-secondary mt-3" @click="$router.push({ name: 'browse' })">
          {{ t('featured.viewAll') }} →
        </button>
      </div>
    </section>

    <!-- Kā tas strādā -->
    <section class="how-section py-5" id="how-it-works">
      <div class="container-xl">
        <h2 class="section-title text-center">{{ t('how.title') }}</h2>
        <p class="text-center text-muted mt-2 mb-5">{{ t('how.subtitle') }}</p>
        <div class="row row-cols-1 row-cols-md-3 g-4 justify-content-center">
          <div class="col" v-for="(step, i) in steps" :key="step.number">
            <div class="step h-100 scroll-reveal" :style="{ transitionDelay: (i * 0.12) + 's' }">
              <div class="step-icon mb-2">{{ step.icon }}</div>
              <div class="step-number mb-2">{{ step.number }}</div>
              <h3 class="step-title mb-2">{{ step.title }}</h3>
              <p class="step-desc mb-0">{{ step.desc }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Kājene -->
    <footer class="bookloop-footer py-4">
      <div class="container-xl d-flex flex-wrap align-items-center gap-4">
        <div class="d-flex align-items-center gap-2 flex-grow-1">
          <span class="brand-icon" style="color:#5a9b5e">⇄</span>
          <span class="brand-name text-white">BookLoop</span>
          <span class="footer-tagline ms-2">{{ t('footer.tagline') }}</span>
        </div>
        <div class="footer-links d-flex gap-4">
          <button class="footer-link-btn" @click="$router.push({ name: 'about' })">{{ t('footer.about') }}</button>
          <button class="footer-link-btn" @click="scrollToHowItWorks">{{ t('footer.howItWorks') }}</button>
          <button class="footer-link-btn" @click="$router.push({ name: 'browse' })">{{ t('footer.browse') }}</button>
        </div>
        <p class="footer-copy w-100 mb-0 mt-2">{{ t('footer.copy') }}</p>
      </div>
    </footer>

  </div>

  <!-- Grāmatas detaļas -->
  <BookDetailModal
    :book="detailBook"
    :blocked="detailBookBlocked"
    @close="detailBook = null; detailBookBlocked = false"
    @swap="requestSwap"
    @message="goToMessages"
    @profile="(user) => $router.push({ name: 'userProfile', params: { id: user.id } })"
  />

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
import homeMethods from '../homeLogic.js'
import langMixin from '../langMixin.js'
import { authStore } from '../authStore.js'
import SwapModal from './SwapModal.vue'
import BookDetailModal from './BookDetailModal.vue'
import AppNavbar from './AppNavbar.vue'
import { coverColor } from '../coverColor.js'

export default {
  name: 'Home',

  components: { SwapModal, BookDetailModal, AppNavbar },

  mixins: [homeMethods, langMixin],

  mounted() {
    this.fetchFeaturedSections()

    this.observer = new IntersectionObserver(entries => {
      entries.forEach(e => {
        if (!e.isIntersecting) return
        e.target.classList.add('revealed')
        this.observer.unobserve(e.target)
      })
    }, { threshold: 0.12 })

    this.$nextTick(() => {
      document.querySelectorAll('.scroll-reveal').forEach(el => this.observer.observe(el))
    })
  },

  beforeUnmount() {
    this.observer?.disconnect()
  },

  methods: {
    async fetchFeaturedSections() {
      this.booksLoading = true
      try {
        const [recent, pop, rated, statsRes] = await Promise.all([
          axios.get('/api/browse', { params: { sort: 'newest', per_page: 6, page: 1 } }),
          axios.get('/api/browse', { params: { sort: 'popular', per_page: 6, page: 1 } }),
          axios.get('/api/browse', { params: { sort: 'top_rated', per_page: 6, page: 1 } }),
          axios.get('/api/stats'),
        ])
        this.recentBooks = recent.data.data
        this.popularBooks = pop.data.data
        this.topRatedBooks = rated.data.data.filter(b => b.ratings_count > 0)
        this.stats = statsRes.data
      } catch {
        // klusām ignorē
      } finally {
        this.booksLoading = false
      }
    },

    browseTo() {
      const q = {}
      if (this.homeSearch.trim()) q.search = this.homeSearch.trim()
      this.$router.push({ name: 'browse', query: q })
    },

    goToMessages(user) {
      if (!authStore.user) {
        this.$router.push({ name: 'login' })
        return
      }
      this.$router.push({ name: 'messages', query: { userId: user.id, userName: user.name } })
    },

    async openDetail(book) {
      this.detailBook = book
      this.detailBookBlocked = false
      if (authStore.user && book.user?.id) {
        try {
          const { data } = await axios.get(`/api/users/${book.user.id}`)
          this.detailBookBlocked = data.is_blocked ?? false
        } catch { /* ignore */ }
      }
    },

    async requestSwap(book) {
      this.detailBook = null
      this.swapModal.wantedBook    = book
      this.swapModal.selectedBookId = null
      this.swapModal.error         = ''
      this.swapModal.myBooks       = []
      this.swapModal.open          = true
      if (authStore.user) {
        try {
          const { data } = await axios.get('/api/books')
          this.swapModal.myBooks = data.filter(b => b.status === 'Available')
        } catch { /* ignorē */ }
      }
    },

    closeSwapModal() { this.swapModal.open = false },

    async sendSwapRequest() {
      this.swapModal.error   = ''
      this.swapModal.sending = true
      try {
        await axios.post('/api/swap-requests', {
          offered_book_id: this.swapModal.selectedBookId,
          wanted_book_id:  this.swapModal.wantedBook.id,
        })
        // noņem no visu sarakstiem
        const id = this.swapModal.wantedBook.id
        this.recentBooks   = this.recentBooks.filter(b => b.id !== id)
        this.popularBooks  = this.popularBooks.filter(b => b.id !== id)
        this.topRatedBooks = this.topRatedBooks.filter(b => b.id !== id)
        this.closeSwapModal()
      } catch (err) {
        this.swapModal.error = err.response?.data?.message || 'Something went wrong.'
      } finally {
        this.swapModal.sending = false
      }
    },

    coverColor,
  },

  data() {
    return {
      authStore,
      booksLoading: true,
      stats: null,
      recentBooks: [],
      popularBooks: [],
      topRatedBooks: [],
      homeSearch: '',
      detailBook: null,
      detailBookBlocked: false,
      swapModal: {
        open: false, wantedBook: null, myBooks: [],
        selectedBookId: null, sending: false, error: '',
      },
    }
  },

  watch: {
  }
}
</script>
