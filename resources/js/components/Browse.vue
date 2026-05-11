<template>
  <div class="bookloop-home">

    <AppNavbar>
      <template v-if="!authStore.user">
        <button class="btn btn-sm btn-outline-secondary" @click="$router.push({ name: 'login' })">{{ t('nav.login') }}</button>
        <button class="btn btn-sm btn-primary" @click="$router.push({ name: 'register' })">{{ t('nav.signup') }}</button>
      </template>
    </AppNavbar>

    <section class="browse-header">
      <div class="container-xl">
        <h1 class="browse-header-title">{{ t('browse.title') }}</h1>
        <p class="browse-header-sub">{{ t('browse.subtitle') }}</p>
      </div>
    </section>

    <section class="border-bottom py-4">
      <div class="container-xl">
        <div class="browse-toolbar mb-3">
          <div>
            <h1 class="browse-page-title mb-0">{{ pageTitle }}</h1>
            <p v-if="tab === 'books' && totalBooks > 0" class="text-muted small mb-0">
              {{ totalBooks }} {{ t('browse.resultsCount') }}
            </p>
            <p v-else-if="tab === 'users'" class="text-muted small mb-0">
              {{ userTotal }} {{ t('browse.usersCount') }}
            </p>
          </div>
          <div class="view-toggle">
            <button :class="['view-toggle-btn', tab === 'books' ? 'active' : '']" @click="tab = 'books'" :aria-pressed="tab === 'books'">{{ t('nav.browse') }}</button>
            <button :class="['view-toggle-btn', tab === 'users' ? 'active' : '']" @click="tab = 'users'; userPage = 1; fetchUsers()" :aria-pressed="tab === 'users'">{{ t('browse.users') }}</button>
          </div>
          <div class="d-flex justify-content-end">
            <div class="view-toggle" :style="{ opacity: tab === 'books' ? 1 : 0, pointerEvents: tab === 'books' ? 'auto' : 'none' }" style="transition: opacity 0.2s">
              <button :class="['view-toggle-btn', viewMode === 'cards' ? 'active' : '']" @click="viewMode = 'cards'" :aria-label="t('books.viewCards')" :aria-pressed="viewMode === 'cards'">⊞</button>
              <button :class="['view-toggle-btn', viewMode === 'table' ? 'active' : '']" @click="viewMode = 'table'" :aria-label="t('books.viewTable')" :aria-pressed="viewMode === 'table'">☰</button>
            </div>
          </div>
        </div>

        <div class="filter-area">
        <transition name="tab-fade" mode="out-in">
        <div v-if="tab === 'books'" key="books-filters">
        <!-- meklēšana vienmēr redzama -->
        <div class="d-flex gap-2 mb-2">
          <input v-model="searchQuery" class="form-control" :placeholder="t('search.placeholder')" :aria-label="t('search.placeholder')" @input="debouncedSearch" @keyup.enter="applyFilters" />
          <button class="filter-more-btn d-lg-none" :class="{ active: showMoreFilters }" @click="showMoreFilters = !showMoreFilters" :aria-label="t('search.moreFilters')">
            <svg aria-hidden="true" width="16" height="13" viewBox="0 0 16 13" fill="none">
              <rect x="0" y="0"  width="16" height="2" rx="1" fill="currentColor"/>
              <rect x="3" y="5.5" width="10" height="2" rx="1" fill="currentColor"/>
              <rect x="6" y="11" width="4"  height="2" rx="1" fill="currentColor"/>
            </svg>
            <span class="filter-more-dot" v-if="genreFilters.length || langFilters.length || sortBy !== 'title_asc'"></span>
          </button>
        </div>

        <!-- papildu filtri — mobilajā slēpti līdz pogai -->
        <div v-if="showMoreFilters || isDesktop" class="d-flex flex-wrap gap-2 mb-2">
          <div class="filter-suggest-wrap" style="min-width:150px;flex:1">
            <div class="filter-suggest-inner">
              <input v-model="genreSearch" class="form-control"
                :placeholder="genreFilters.length ? t('search.addGenre') : t('search.allGenres')"
                :aria-label="t('search.allGenres')"
                autocomplete="off"
                @focus="showGenreSugg = true" @input="showGenreSugg = true" @blur="hideGenreSugg" />
              <button v-if="genreFilters.length" class="filter-clear-btn" :aria-label="t('books.clearFilters')" @mousedown.prevent="clearGenre">×</button>
            </div>
            <ul v-if="showGenreSugg && filteredGenres.length" class="suggest-list">
              <li v-for="g in filteredGenres" :key="g.value" @mousedown.prevent="selectGenre(g)">{{ g.label }}</li>
            </ul>
          </div>

          <div class="filter-suggest-wrap" style="min-width:130px;flex:1">
            <div class="filter-suggest-inner">
              <input v-model="langSearch" class="form-control"
                :placeholder="langFilters.length ? t('search.addLang') : t('search.allLanguages')"
                :aria-label="t('search.allLanguages')"
                autocomplete="off"
                @focus="showLangSugg = true" @input="showLangSugg = true" @blur="hideLangSugg" />
              <button v-if="langFilters.length" class="filter-clear-btn" :aria-label="t('books.clearFilters')" @mousedown.prevent="clearLang">×</button>
            </div>
            <ul v-if="showLangSugg && filteredLangs.length" class="suggest-list">
              <li v-for="l in filteredLangs" :key="l.value" @mousedown.prevent="selectLang(l)">{{ l.label }}</li>
            </ul>
          </div>

          <!-- kārtošana tikai kartiņu skatā, tabulā ir savas kolonnas -->
          <div v-show="viewMode === 'cards'" style="min-width:150px;flex:1">
            <select v-model="sortBy" class="form-select" :aria-label="t('sort.titleAsc')" @change="applyFilters">
              <option value="title_asc">{{ t('sort.titleAsc') }}</option>
              <option value="title_desc">{{ t('sort.titleDesc') }}</option>
              <option value="author_asc">{{ t('sort.authorAsc') }}</option>
              <option value="author_desc">{{ t('sort.authorDesc') }}</option>
              <option value="newest">{{ t('featured.recent') }}</option>
              <option value="popular">{{ t('featured.popular') }}</option>
              <option value="top_rated">{{ t('featured.topRated') }}</option>
            </select>
          </div>
        </div>

        <!-- aktīvie filtra tagi -->
        <div v-if="genreFilters.length || langFilters.length || searchQuery || sortBy !== 'title_asc'" class="d-flex flex-wrap align-items-center gap-1 mt-1">
          <span v-for="g in genreFilters" :key="g" class="table-genre-tag">
            {{ t('genre.' + g) }}
            <button :aria-label="'Noņemt: ' + t('genre.' + g)" @mousedown.prevent="removeGenre(g)">×</button>
          </span>
          <span v-for="l in langFilters" :key="l" class="table-genre-tag" style="background:#c47c28">
            {{ t('lang.' + l) }}
            <button :aria-label="'Noņemt: ' + t('lang.' + l)" @mousedown.prevent="removeLang(l)">×</button>
          </span>
          <button class="btn btn-sm btn-outline-secondary ms-1" @mousedown.prevent="clearAll">{{ t('books.clearFilters') }}</button>
        </div>
        </div>

        <div v-else key="users-search">
          <input v-model="userSearch" class="form-control" :placeholder="t('browse.searchUsers')" :aria-label="t('browse.searchUsers')" @input="onUserSearch" style="max-width:420px" />
        </div>
        </transition>
        </div>
      </div>
    </section>

    <section v-show="tab === 'books'" class="container-xl py-4" style="min-height:60vh">

      <div v-if="loadingBooks" class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3">
        <div v-for="n in 10" :key="n" class="col">
          <div class="card h-100 book-card border skeleton-card">
            <div class="skeleton-cover"></div>
            <div class="card-body p-3">
              <div class="skeleton-line mb-2" style="width:80%"></div>
              <div class="skeleton-line mb-3" style="width:55%"></div>
              <div class="skeleton-line" style="width:40%"></div>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="books.length === 0" class="text-center py-5 text-muted">
        <template v-if="hasActiveFilters">
          <div class="fs-1">📭</div>
          <p class="mb-3">{{ t('books.empty') }}</p>
          <button class="btn btn-outline-secondary" @click="clearAll">{{ t('books.clearFilters') }}</button>
        </template>
        <template v-else>
          <div class="fs-1">📚</div>
          <p class="fw-semibold mb-1" style="color:var(--ink)">{{ t('books.emptyNoFilters') }}</p>
          <p class="small mb-3">{{ t('books.emptyNoFiltersSub') }}</p>
          <button v-if="!authStore.user" class="btn btn-primary" @click="$router.push({ name: 'register' })">{{ t('nav.signup') }}</button>
          <button v-else class="btn btn-primary" @click="$router.push({ name: 'dashboard' })">{{ t('dash.addFirst') }}</button>
        </template>
      </div>

      <div v-else-if="viewMode === 'cards'" class="row row-cols-2 row-cols-md-4 row-cols-lg-5 g-3">
        <div v-for="book in books" :key="book.id" class="col">
          <div class="card h-100 book-card border" style="cursor:pointer; position:relative" @click="openDetail(book)">
            <div v-if="book.status === 'Pending'" class="pending-overlay"></div>
            <div v-if="isNew(book)" class="new-ribbon">{{ t('books.newRibbon') }}</div>
            <div class="book-card-cover" :style="!book.cover_image ? { background: coverColor(book) } : {}">
              <img v-if="book.cover_image" :src="'/storage/' + book.cover_image" :alt="book.title" class="book-card-cover-img" />
              <span class="book-card-genre">{{ t('genre.' + book.genre) }}</span>
            </div>
            <div class="card-body d-flex flex-column p-3">
              <h3 class="book-card-title mb-1">{{ book.title }}</h3>
              <p class="book-card-author mb-1">{{ book.author }}</p>
              <p class="book-card-owner mb-2">
                {{ t('books.ownedBy') }} <span class="owner-link" @click.stop="$router.push({ name: 'userProfile', params: { id: book.user.id } })">{{ book.user?.name }}</span>
              </p>
              <div class="d-flex flex-wrap gap-1 mb-2">
                <span class="tag">{{ t('lang.' + book.language) }}</span>
                <span class="tag" :class="conditionClass(book.condition)">{{ book.condition }}</span>
                <span class="tag" :class="book.status === 'Available' ? 'tag-green' : 'tag-yellow'">{{ t('books.status.' + book.status) }}</span>
              </div>
              <div class="mt-auto book-card-rating">
                <template v-if="book.ratings_count > 0">
                  <span v-for="s in 5" :key="s" :class="s <= Math.round(book.ratings_avg_stars) ? 'star filled' : 'star'">★</span>
                  <span class="rating-count">({{ book.ratings_count }})</span>
                </template>
                <span v-else class="rating-none">{{ t('books.noRatings') }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="book-table-wrap">
        <table class="book-table">
          <thead>
            <tr>
              <th @click="sortTable('title')">{{ t('books.colTitle') }} <span class="sort-arrow">{{ tableSortArrow('title') }}</span></th>
              <th @click="sortTable('author')">{{ t('books.colAuthor') }} <span class="sort-arrow">{{ tableSortArrow('author') }}</span></th>
              <th @click="sortTable('genre')">{{ t('books.colGenre') }} <span class="sort-arrow">{{ tableSortArrow('genre') }}</span></th>
              <th @click="sortTable('language')">{{ t('books.colLang') }} <span class="sort-arrow">{{ tableSortArrow('language') }}</span></th>
              <th @click="sortTable('condition')">{{ t('books.colCondition') }} <span class="sort-arrow">{{ tableSortArrow('condition') }}</span></th>
              <th @click="sortTable('status')">{{ t('books.colStatus') }} <span class="sort-arrow">{{ tableSortArrow('status') }}</span></th>
              <th>{{ t('books.colOwner') }}</th>
              <th>{{ t('books.colActions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="book in sortedBooks" :key="book.id" style="cursor:pointer" @click="openDetail(book)">
              <td>{{ book.title }}</td>
              <td>{{ book.author }}</td>
              <td>{{ t('genre.' + book.genre) }}</td>
              <td>{{ t('lang.' + book.language) }}</td>
              <td>{{ book.condition }}</td>
              <td><span class="tag" :class="book.status === 'Available' ? 'tag-green' : 'tag-yellow'">{{ t('books.status.' + book.status) }}</span></td>
              <td><span class="owner-link" @click.stop="$router.push({ name: 'userProfile', params: { id: book.user.id } })">{{ book.user?.name }}</span></td>
              <td @click.stop>
                <button class="btn-swap btn-swap-sm" :disabled="book.status !== 'Available'" @click="openSwap(book)">{{ t('books.requestSwap') }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pagination :current="currentPage" :total="lastPage" @change="goToPage" />

    </section>

    <section v-show="tab === 'users'" class="container-xl py-4" style="min-height:60vh">
      <div v-if="loadingUsers" class="text-center py-5 text-muted">
        <div class="fs-1">⏳</div>
        <p>{{ t('dash.loading') }}</p>
      </div>
      <div v-else-if="users.length === 0" class="text-center py-5 text-muted">
        <div class="fs-1">🔍</div>
        <p>{{ t('browse.noUsers') }}</p>
      </div>
      <div v-else class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
        <div v-for="user in users" :key="user.id" class="col">
          <div class="user-card" role="button" tabindex="0"
            @click="$router.push({ name: 'userProfile', params: { id: user.id } })"
            @keyup.enter="$router.push({ name: 'userProfile', params: { id: user.id } })">
            <div class="user-card-top">
              <div class="user-card-avatar" :style="{ background: avatarColor(user) }">{{ user.name[0].toUpperCase() }}</div>
              <div class="user-card-info">
                <p class="user-card-name">{{ user.name }}</p>
                <p class="user-card-meta">{{ user.books_count }} {{ t('browse.availableBooks') }}</p>
              </div>
              <span class="user-card-arrow">→</span>
            </div>
            <div v-if="user.preview_books && user.preview_books.length" class="user-card-shelf">
              <div v-for="book in user.preview_books" :key="book.id" class="user-shelf-book" :style="book.cover_image ? {} : { background: coverColor(book) }">
                <img v-if="book.cover_image" :src="'/storage/' + book.cover_image" :alt="book.genre" />
              </div>
              <div v-if="user.books_count > user.preview_books.length" class="user-shelf-more">+{{ user.books_count - user.preview_books.length }}</div>
            </div>
          </div>
        </div>
      </div>

      <Pagination :current="userPage" :total="userLastPage" @change="goToUserPage" />
    </section>

    <AppFooter />

  </div>

  <BookDetailModal
    :book="detailBook"
    :blocked="detailBookBlocked"
    @close="detailBook = null; detailBookBlocked = false"
    @swap="openSwap"
    @message="goToMessages"
    @profile="(user) => $router.push({ name: 'userProfile', params: { id: user.id } })"
    @suggest="openDetail"
  />

  <SwapModal
    :open="swapModal.open"
    :wanted-book="swapModal.wantedBook"
    :my-books="swapModal.myBooks"
    :selected-book-id="swapModal.selectedBookId"
    :sending="swapModal.sending"
    :error="swapModal.error"
    :success="swapModal.success"
    @update:selected-book-id="swapModal.selectedBookId = $event"
    @close="swapModal.open = false; swapModal.success = false"
    @send="sendSwap"
  />
</template>

<script>
import axios from 'axios'
import langMixin from '../langMixin.js'
import { authStore } from '../authStore.js'
import { coverColor } from '../coverColor.js'
import { conditionClass } from '../conditionClass.js'
import BookDetailModal from './BookDetailModal.vue'
import SwapModal from './SwapModal.vue'
import AppNavbar from './AppNavbar.vue'
import AppFooter from './AppFooter.vue'
import Pagination from './Pagination.vue'

export default {
  name: 'Browse',

  components: { BookDetailModal, SwapModal, AppNavbar, AppFooter, Pagination },

  mixins: [langMixin],

  data() {
    return {
      authStore,
      searchQuery: '',
      langFilters: [],
      genreSearch: '',
      langSearch: '',
      showGenreSugg: false,
      showLangSugg: false,
      sortBy: 'title_asc',
      currentPage: 1,
      lastPage: 1,
      totalBooks: 0,
      loadingBooks: false,
      viewMode: 'cards',
      tableSort: { col: 'title', dir: 'asc' },
      genreFilters: [],
      books: [],
      tab: 'books',
      users: [],
      userSearch: '',
      loadingUsers: false,
      userPage: 1,
      userLastPage: 1,
      userTotal: 0,
      detailBook: null,
      detailBookBlocked: false,
      swapModal: {
        open: false, wantedBook: null, myBooks: [],
        selectedBookId: null, sending: false, error: '', success: false,
      },
      showMoreFilters: false,
      isDesktop: window.innerWidth >= 992,
    }
  },

  computed: {
    hasActiveFilters() {
      return !!(this.searchQuery || this.genreFilters.length || this.langFilters.length || this.sortBy !== 'title_asc')
    },

    pageTitle() {
      if (this.tab === 'users') return this.t('browse.users')
      if (this.searchQuery) return `${this.t('browse.searchFor')}: "${this.searchQuery}"`
      switch (this.sortBy) {
        case 'popular': return this.t('featured.popular')
        case 'top_rated': return this.t('featured.topRated')
        case 'newest': return this.t('featured.recent')
        default: return this.t('browse.allBooks')
      }
    },
    sortedBooks() {
      let list = [...this.books]
      if (this.genreFilters.length) list = list.filter(b => this.genreFilters.includes(b.genre))
      const { col, dir } = this.tableSort
      list.sort((a, b) => {
        const av = (a[col] || '').toLowerCase()
        const bv = (b[col] || '').toLowerCase()
        if (av < bv) return dir === 'asc' ? -1 : 1
        if (av > bv) return dir === 'asc' ? 1 : -1
        return 0
      })
      return list
    },
    genreOptions() {
      return ['Romāns','Dzeja','Zinātne','Vēsture','Klasika','Bērnu grāmatas',
        'Fantāzija','Detektīvs','Trillenis','Romantika','Piedzīvojumi',
        'Biogrāfija','Pašpalīdzība','Filozofija','Cits']
        .map(v => ({ value: v, label: this.t('genre.' + v) }))
    },
    filteredGenres() {
      let opts = this.genreOptions.filter(g => !this.genreFilters.includes(g.value))
      if (!this.genreSearch) return opts
      return opts.filter(g => g.label.toLowerCase().includes(this.genreSearch.toLowerCase()))
    },
    langOptions() {
      return ['Latviešu','English','Русский'].map(v => ({ value: v, label: this.t('lang.' + v) }))
    },
    filteredLangs() {
      let opts = this.langOptions.filter(l => !this.langFilters.includes(l.value))
      if (!this.langSearch) return opts
      return opts.filter(l => l.label.toLowerCase().includes(this.langSearch.toLowerCase()))
    },
  },

  mounted() {
    this.readRouteParams(this.$route.query)
    this.fetchBooks()
    this._onResize = () => { this.isDesktop = window.innerWidth >= 992 }
    window.addEventListener('resize', this._onResize)
  },

  beforeUnmount() {
    window.removeEventListener('resize', this._onResize)
  },

  watch: {
    '$route.query': {
      handler(q) {
        this.readRouteParams(q)
        this.fetchBooks()
      }
    },
  },

  methods: {
    debouncedSearch() {
      clearTimeout(this._searchTimer)
      this._searchTimer = setTimeout(() => this.applyFilters(), 350)
    },

    readRouteParams(q) {
      this.searchQuery = q.search || ''
      this.sortBy = q.sort || 'title_asc'
      this.langFilters = q.languages ? q.languages.split(',') : []
      this.langSearch = ''
      this.genreFilters = q.genres ? q.genres.split(',') : []
      this.currentPage = parseInt(q.page) || 1
    },

    buildQuery() {
      const q = {}
      if (this.searchQuery.trim()) q.search = this.searchQuery.trim()
      if (this.sortBy !== 'title_asc') q.sort = this.sortBy
      if (this.langFilters.length) q.languages = this.langFilters.join(',')
      if (this.genreFilters.length) q.genres = this.genreFilters.join(',')
      if (this.currentPage > 1) q.page = this.currentPage
      return q
    },

    applyFilters() {
      this.currentPage = 1
      this.$router.push({ name: 'browse', query: this.buildQuery() })
    },

    goToPage(n) {
      this.currentPage = n
      this.$router.push({ name: 'browse', query: this.buildQuery() })
    },

    async fetchBooks() {
      this.loadingBooks = true
      try {
        const perPage = this.viewMode === 'table' ? 500 : 20
        const { data } = await axios.get('/api/browse', {
          params: {
            search: this.searchQuery.trim() || undefined,
            genres: this.genreFilters.length ? this.genreFilters : undefined,
            languages: this.langFilters.length ? this.langFilters : undefined,
            sort: this.sortBy,
            page: this.currentPage,
            per_page: perPage,
          },
        })
        this.books = data.data
        this.currentPage = data.current_page
        this.lastPage = data.last_page
        this.totalBooks = data.total
      } catch {
        // atstāj sarakstu tukšu
      } finally {
        this.loadingBooks = false
      }
    },

    clearAll() {
      this.$router.push({ name: 'browse' })
    },

    selectGenre(g) {
      if (!this.genreFilters.includes(g.value)) this.genreFilters.push(g.value)
      this.genreSearch = ''
      this.showGenreSugg = false
      this.applyFilters()
    },
    clearGenre() {
      this.genreFilters = []
      this.genreSearch = ''
      this.applyFilters()
    },
    removeGenre(genre) {
      this.genreFilters = this.genreFilters.filter(g => g !== genre)
      this.applyFilters()
    },
    hideGenreSugg() { setTimeout(() => { this.showGenreSugg = false }, 150) },

    selectLang(l) {
      if (!this.langFilters.includes(l.value)) this.langFilters.push(l.value)
      this.langSearch = ''
      this.showLangSugg = false
      this.applyFilters()
    },
    clearLang() {
      this.langFilters = []
      this.langSearch = ''
      this.applyFilters()
    },
    removeLang(lang) {
      this.langFilters = this.langFilters.filter(l => l !== lang)
      this.applyFilters()
    },
    hideLangSugg() { setTimeout(() => { this.showLangSugg = false }, 150) },

    // tas pats stabiņš — apgriež virzienu, citādi no sākuma
    sortTable(col) {
      if (this.tableSort.col === col) {
        this.tableSort.dir = this.tableSort.dir === 'asc' ? 'desc' : 'asc'
      } else {
        this.tableSort.col = col
        this.tableSort.dir = 'asc'
      }
    },
    tableSortArrow(col) {
      if (this.tableSort.col !== col) return '↕'
      return this.tableSort.dir === 'asc' ? '↑' : '↓'
    },

    async openSwap(book) {
      this.detailBook = null
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

    async sendSwap() {
      this.swapModal.error = ''
      this.swapModal.sending = true
      try {
        await axios.post('/api/swap-requests', {
          offered_book_id: this.swapModal.selectedBookId,
          wanted_book_id: this.swapModal.wantedBook.id,
        })
        this.books = this.books.filter(b => b.id !== this.swapModal.wantedBook.id)
        this.swapModal.success = true
      } catch (err) {
        this.swapModal.error = err.response?.data?.message || this.t('dash.genericError')
      } finally {
        this.swapModal.sending = false
      }
    },

    goToMessages(user) {
      if (!authStore.user) {
        this.$router.push({ name: 'login' })
        return
      }
      this.$router.push({ name: 'messages', query: { userId: user.id, userName: user.name } })
    },

    onUserSearch() {
      this.userPage = 1
      this.fetchUsers()
    },

    goToUserPage(n) {
      this.userPage = n
      this.fetchUsers()
      window.scrollTo({ top: 0, behavior: 'smooth' })
    },

    async fetchUsers() {
      this.loadingUsers = true
      try {
        const { data } = await axios.get('/api/users', {
          params: {
            search: this.userSearch.trim() || undefined,
            page: this.userPage,
          }
        })
        this.users = data.data
        this.userPage = data.current_page
        this.userLastPage = data.last_page
        this.userTotal = data.total
      } catch { /* ignorē */ } finally {
        this.loadingUsers = false
      }
    },

    async openDetail(book) {
      this.detailBook = book
      this.detailBookBlocked = false
      if (authStore.user && book.user?.id) {
        try {
          const { data } = await axios.get(`/api/users/${book.user.id}`)
          this.detailBookBlocked = (data.is_blocked || data.they_blocked_me) ?? false
        } catch { /* ignorē */ }
      }
    },

    coverColor,
    conditionClass,

    isNew(book) {
      return (Date.now() - new Date(book.created_at)) < 7 * 24 * 60 * 60 * 1000
    },

    avatarColor(user) {
      const colors = ['#4f7c52','#7c5c4f','#4f5d7c','#7c4f6f','#4f7a7c','#7c7a4f']
      return colors[user.id % colors.length]
    },
  }
}
</script>
