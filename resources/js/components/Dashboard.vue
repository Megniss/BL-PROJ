<template>
  <div class="dashboard-page">

    <!-- Navigācija -->
    <AppNavbar>
      <!-- Paziņojumu zvans -->
      <div class="notif-wrap" ref="notifWrap">
        <button class="notif-bell" @click="toggleNotifs" :aria-label="t('notif.title') + (unreadCount > 0 ? ' (' + unreadCount + ')' : '')">
          <span aria-hidden="true">🔔</span>
          <span v-if="unreadCount > 0" class="notif-badge" aria-hidden="true">{{ unreadCount }}</span>
        </button>
        <div v-if="showNotifs" class="notif-dropdown">
          <div class="notif-header">
            <span>{{ t('notif.title') }}</span>
            <button v-if="unreadNotifCount > 0" class="notif-read-all" @click.stop="markAllRead">{{ t('notif.markAllRead') }}</button>
          </div>
          <template v-if="pendingIncoming.length > 0">
            <div class="notif-section-label">{{ t('notif.swapRequests') }}</div>
            <div v-for="req in pendingIncoming" :key="'req-'+req.id" class="notif-item notif-swap unread">
              <p class="notif-msg">
                <strong>{{ req.requester.name }}</strong> {{ t('notif.wants') }}
                <strong>{{ req.wanted_book.title }}</strong> {{ t('notif.andOffers') }}
                <strong>{{ req.offered_book.title }}</strong>.
              </p>
              <div class="notif-swap-actions">
                <button class="btn btn-primary btn-sm" @click.stop="acceptSwap(req)">{{ t('notif.accept') }}</button>
                <button class="btn btn-outline-secondary btn-sm" @click.stop="declineSwap(req)">{{ t('notif.decline') }}</button>
              </div>
            </div>
          </template>
          <template v-if="notifications.length > 0">
            <div v-if="pendingIncoming.length > 0" class="notif-section-label">{{ t('notif.activity') }}</div>
            <div v-for="n in notifications" :key="n.id" class="notif-item" :class="{ unread: !n.read_at }" @click="handleNotifClick(n)">
              <p class="notif-msg">{{ n.data.message }}</p>
              <span class="notif-time">{{ formatTime(n.created_at) }}</span>
            </div>
          </template>
          <div v-if="pendingIncoming.length === 0 && notifications.length === 0" class="notif-empty">{{ t('notif.empty') }}</div>
          <div v-if="notifsNextPage" class="notif-load-more">
            <button class="notif-load-more-btn" @click.stop="loadMoreNotifs">{{ t('notif.loadMore') }}</button>
          </div>
        </div>
      </div>

      <div class="position-relative">
        <button class="btn btn-sm btn-outline-secondary" @click="$router.push({ name: 'messages' })">
          {{ t('nav.messages') }}
          <span v-if="unreadMessages > 0" class="notif-badge msg-badge">{{ unreadMessages }}</span>
        </button>
      </div>
    </AppNavbar>

    <!-- Galvenais saturs -->
    <div class="container-xl py-4 px-3 px-md-4">

      <!-- Galvene -->
      <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
          <h1 class="dashboard-title mb-1">{{ t('dash.title') }}</h1>
          <p class="dashboard-sub mb-0">{{ t('dash.sub') }}</p>
        </div>
        <button class="btn btn-primary" @click="openAddModal">{{ t('dash.addBook') }}</button>
      </div>

      <div v-if="fetchError" class="alert alert-danger mb-4" role="alert">{{ t('dash.fetchError') }}</div>
      <div v-if="actionError" class="alert alert-danger mb-4" role="alert">{{ actionError }}</div>

      <div v-if="loading" class="text-center py-5 text-muted">
        <div class="fs-1">⏳</div>
        <p>{{ t('dash.loading') }}</p>
      </div>

      <div v-else-if="books.length === 0" class="empty-library">
        <div class="empty-library-shelf">
          <div class="empty-shelf-slot"></div>
          <div class="empty-shelf-slot"></div>
          <div class="empty-shelf-icon">📚</div>
          <div class="empty-shelf-slot"></div>
          <div class="empty-shelf-slot"></div>
          <div class="empty-shelf-slot"></div>
        </div>
        <h2 class="empty-library-title">{{ t('dash.empty') }}</h2>
        <p class="empty-library-sub">{{ t('dash.emptySub') }}</p>
        <button class="btn btn-primary px-4" @click="openAddModal">{{ t('dash.addFirst') }}</button>
      </div>

      <!-- Ienākošie pieprasījumi -->
      <template v-if="pendingIncoming.length > 0">
        <h2 class="dash-section-title mb-3">{{ t('dash.receivedReqs') }}</h2>
        <div class="d-flex flex-column gap-2 mb-4">
          <div v-for="req in pendingIncoming" :key="'in-'+req.id" class="card border">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3 p-3">
              <p class="mb-0 small flex-grow-1">
                <strong>{{ req.requester.name }}</strong> {{ t('notif.wants') }}
                <strong>{{ req.wanted_book.title }}</strong> {{ t('notif.andOffers') }}
                <strong>{{ req.offered_book.title }}</strong>.
              </p>
              <div class="d-flex gap-2">
                <button class="btn btn-primary btn-sm" @click="acceptSwap(req)">{{ t('notif.accept') }}</button>
                <button class="btn btn-outline-secondary btn-sm" @click="declineSwap(req)">{{ t('notif.decline') }}</button>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Nosūtītie apmaiņas pieprasījumi -->
      <template v-if="outgoing.length > 0">
        <h2 class="dash-section-title mb-3">{{ t('dash.sentReqs') }}</h2>
        <div class="d-flex flex-column gap-2 mb-4">
          <div v-for="req in outgoing" :key="req.id" class="card border">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3 p-3">
              <div class="flex-grow-1">
                <p class="mb-2 small">
                  {{ t('dash.youOffered') }} <strong>{{ req.offered_book.title }}</strong> {{ t('dash.for') }}
                  <strong>{{ req.wanted_book.title }}</strong>
                  ({{ t('dash.ownedBy') }} <strong>{{ req.wanted_book.user?.name }}</strong>).
                </p>
                <span class="tag" :class="statusClass(req.status)">{{ req.status }}</span>
              </div>
              <div>
                <button v-if="req.status === 'pending'" class="btn-dismiss btn-cancel" @click="cancelSwap(req)">{{ t('dash.cancel') }}</button>
                <button v-else class="btn-dismiss" @click="dismissSwap(req)">{{ t('dash.dismiss') }}</button>
              </div>
            </div>
          </div>
        </div>
      </template>

      <h2 class="dash-section-title mb-3">{{ t('dash.myBooks') }}</h2>

      <!-- Grāmatu režģis -->
      <div v-if="!loading && books.length > 0" class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
        <div v-for="book in books" :key="book.id" class="col">
          <div class="card h-100 book-card border">
            <div class="book-card-cover" :style="!book.cover_image ? { background: coverColor(book) } : {}">
              <img v-if="book.cover_image" :src="'/storage/' + book.cover_image" :alt="book.title" class="book-card-cover-img" />
              <span class="book-card-genre">{{ book.genre }}</span>
              <div v-if="book.status === 'Pending'" class="pending-overlay"></div>
              <!-- edit/delete overlay -->
              <div class="dash-card-actions">
                <button class="dash-action-btn" :aria-label="t('modal.editBook')" @click="openEditModal(book)">✏️</button>
                <button class="dash-action-btn dash-action-btn--delete" :aria-label="t('modal.deleteTitle')" @click="confirmDelete(book)">🗑️</button>
              </div>
            </div>
            <div class="card-body d-flex flex-column p-3">
              <h3 class="book-card-title mb-1">{{ book.title }}</h3>
              <p class="book-card-author mb-2">{{ book.author }}</p>
              <div class="d-flex flex-wrap gap-1 mt-auto">
                <span class="tag" :class="book.status === 'Available' ? 'tag-green' : 'tag-yellow'">{{ book.status }}</span>
                <span class="tag">{{ book.condition }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Grāmatas pievienošanas / rediģēšanas modālis -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal" role="dialog" aria-modal="true" aria-labelledby="book-modal-title">
      <div class="modal-card">
        <h2 class="modal-title mb-3" id="book-modal-title">{{ editingBook ? t('modal.editBook') : t('modal.addBook') }}</h2>
        <form @submit.prevent="saveBook">
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label fw-semibold">{{ t('modal.fieldTitle') }}</label>
              <input v-model="form.title" class="form-control" type="text" required />
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">{{ t('modal.fieldAuthor') }}</label>
              <input v-model="form.author" class="form-control" type="text" required />
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">{{ t('modal.fieldGenre') }}</label>
              <select v-model="form.genre" class="form-select" required>
                <option value="">{{ t('modal.selectGenre') }}</option>
                <option>Romāns</option><option>Dzeja</option><option>Zinātne</option>
                <option>Vēsture</option><option>Klasika</option><option>Bērnu grāmatas</option>
                <option>Fantāzija</option><option>Detektīvs</option><option>Trillenis</option>
                <option>Romantika</option><option>Piedzīvojumi</option><option>Biogrāfija</option>
                <option>Pašpalīdzība</option><option>Filozofija</option><option>Cits</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">{{ t('modal.fieldLang') }}</label>
              <select v-model="form.language" class="form-select" required>
                <option value="">{{ t('modal.selectLang') }}</option>
                <option>Latviešu</option><option>English</option><option>Русский</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">{{ t('modal.fieldCond') }}</label>
              <select v-model="form.condition" class="form-select" required>
                <option>New</option><option>Good</option><option>Fair</option><option>Worn</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">{{ t('modal.fieldDesc') }}</label>
              <textarea v-model="form.description" class="form-control" rows="3" :placeholder="t('modal.descHint')"></textarea>
            </div>
            <!-- Vāka attēls -->
            <div class="col-12">
              <label class="form-label fw-semibold">{{ t('modal.cover') }}</label>
              <!-- pašreizējais vāks (rediģēšanas režīmā, nav jauns failu) -->
              <div v-if="editingBook && editingBook.cover_image && !coverRemoved && !coverPreview" class="d-flex align-items-center gap-3 mb-2">
                <img :src="'/storage/' + editingBook.cover_image" class="cover-preview-img" :alt="editingBook.title" />
                <button type="button" class="btn btn-sm btn-outline-danger" @click="markRemoveCover">{{ t('modal.removeCover') }}</button>
              </div>
              <!-- priekšskatījums jaunam failam -->
              <div v-if="coverPreview" class="d-flex align-items-center gap-3 mb-2">
                <img :src="coverPreview" class="cover-preview-img" :alt="t('modal.cover')" />
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearCoverFile">{{ t('modal.cancelCover') }}</button>
              </div>
              <input type="file" accept="image/*" class="form-control" @change="onCoverChange" />
            </div>
          </div>
          <div v-if="formError" class="alert alert-danger py-2 px-3 mb-3" role="alert">{{ formError }}</div>
          <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-outline-secondary" @click="closeModal">{{ t('modal.cancel') }}</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? t('modal.saving') : (editingBook ? t('modal.save') : t('modal.addBook')) }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Dzēšanas apstiprinājuma modālis -->
    <div v-if="deletingBook" class="modal-overlay" @click.self="deletingBook = null" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
      <div class="modal-card modal-card--sm">
        <h2 class="modal-title mb-2" id="delete-modal-title">{{ t('modal.deleteTitle') }}</h2>
        <p class="modal-desc mb-4">
          {{ t('modal.deleteConfirm') }} <strong>{{ deletingBook.title }}</strong>?
          {{ t('modal.cannotUndo') }}
        </p>
        <div class="d-flex justify-content-end gap-2">
          <button class="btn btn-outline-secondary" @click="deletingBook = null">{{ t('modal.cancel') }}</button>
          <button class="btn btn-danger" :disabled="saving" @click="deleteBook">
            {{ saving ? t('modal.deleting') : t('modal.delete') }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import axios from 'axios'
import { authStore, clearAuth } from '../authStore.js'
import { coverColor } from '../coverColor.js'
import langMixin from '../langMixin.js'
import AppNavbar from './AppNavbar.vue'

export default {
  name: 'Dashboard',

  components: { AppNavbar },

  mixins: [langMixin],

  data() {
    return {
      authStore,
      books: [],
      incoming: [],
      outgoing: [],
      loading: true,
      fetchError: '',
      actionError: '',
      unreadMessages: 0,
      showModal: false,
      editingBook: null,
      deletingBook: null,
      saving: false,
      formError: '',
      form: {
        title: '', author: '', genre: '', language: '',
        condition: 'Good', status: 'Available', description: ''
      },
      coverFile: null,
      coverPreview: null,
      coverRemoved: false,
      notifications: [],
      notifsNextPage: null,
      unreadNotifCount: 0,
      showNotifs: false,
    }
  },

  computed: {
    pendingIncoming() {
      return this.incoming.filter(r => r.status === 'pending')
    },
    unreadCount() {
      return this.pendingIncoming.length + this.unreadNotifCount
    },
  },

  mounted() {
    this.fetchAll()
    this._notifClickaway = (e) => {
      if (this.showNotifs && this.$refs.notifWrap && !this.$refs.notifWrap.contains(e.target)) {
        this.showNotifs = false
      }
    }
    document.addEventListener('click', this._notifClickaway)
  },

  beforeUnmount() {
    document.removeEventListener('click', this._notifClickaway)
  },

  methods: {
    async fetchAll() {
      this.loading = true
      this.fetchError = ''
      try {
        const [books, incoming, outgoing, notifs, msgCount] = await Promise.all([
          axios.get('/api/books'),
          axios.get('/api/swap-requests/incoming'),
          axios.get('/api/swap-requests/outgoing'),
          axios.get('/api/notifications'),
          axios.get('/api/messages/unread-count'),
        ])
        this.books = books.data
        this.incoming = incoming.data
        this.outgoing = outgoing.data
        this.notifications = notifs.data.data
        this.notifsNextPage = notifs.data.next_page_url
        this.unreadNotifCount = notifs.data.data.filter(n => !n.read_at).length
        this.unreadMessages = msgCount.data.count
      } catch {
        this.fetchError = 'Failed to load your library. Please refresh the page.'
      } finally {
        this.loading = false
      }
    },

    flashError(msg) {
      this.actionError = msg
      setTimeout(() => { this.actionError = '' }, 4000)
    },

    toggleNotifs() {
      this.showNotifs = !this.showNotifs
    },

    async markRead(n) {
      if (n.read_at) return
      try {
        await axios.patch(`/api/notifications/${n.id}/read`)
        n.read_at = new Date().toISOString()
        this.unreadNotifCount = Math.max(0, this.unreadNotifCount - 1)
      } catch {}
    },

    async handleNotifClick(n) {
      await this.markRead(n)
      if (n.data.type === 'message') {
        this.showNotifs = false
        this.$router.push({ name: 'messages' })
      }
    },

    async markAllRead() {
      try {
        await axios.post('/api/notifications/read-all')
        const now = new Date().toISOString()
        this.notifications.forEach(n => { n.read_at = n.read_at || now })
        this.unreadNotifCount = 0
      } catch {}
    },

    async loadMoreNotifs() {
      if (! this.notifsNextPage) return
      try {
        const { data } = await axios.get(this.notifsNextPage)
        this.notifications.push(...data.data)
        this.notifsNextPage = data.next_page_url
      } catch {}
    },

    formatTime(dateStr) {
      return new Date(dateStr).toLocaleDateString('en-GB', {
        day: 'numeric', month: 'short', year: 'numeric'
      })
    },

    statusClass(status) {
      if (status === 'accepted') return 'tag-green'
      if (status === 'pending')  return 'tag-yellow'
      return ''
    },

    async acceptSwap(req) {
      try {
        await axios.patch(`/api/swap-requests/${req.id}/accept`)
        this.showNotifs = false
        await this.fetchAll()
      } catch (err) {
        this.flashError(err.response?.data?.message || 'Something went wrong.')
      }
    },

    async declineSwap(req) {
      try {
        await axios.patch(`/api/swap-requests/${req.id}/decline`)
        this.showNotifs = false
        await this.fetchAll()
      } catch (err) {
        this.flashError(err.response?.data?.message || 'Something went wrong.')
      }
    },

    async cancelSwap(req) {
      if (!confirm(`Cancel your swap request for "${req.wanted_book.title}"?`)) return
      try {
        await axios.delete(`/api/swap-requests/${req.id}`)
        await this.fetchAll()
      } catch (err) {
        this.flashError(err.response?.data?.message || 'Something went wrong.')
      }
    },

    async dismissSwap(req) {
      try {
        await axios.delete(`/api/swap-requests/${req.id}`)
        this.incoming = this.incoming.filter(r => r.id !== req.id)
        this.outgoing = this.outgoing.filter(r => r.id !== req.id)
      } catch (err) {
        this.flashError(err.response?.data?.message || 'Something went wrong.')
      }
    },

    openAddModal() {
      this.editingBook = null
      this.form = { title: '', author: '', genre: '', language: '', condition: 'Good', status: 'Available', description: '' }
      this.formError = ''
      this.coverFile = null
      this.coverPreview = null
      this.coverRemoved = false
      this.showModal = true
    },

    openEditModal(book) {
      this.editingBook = book
      this.form = { ...book }
      this.formError = ''
      this.coverFile = null
      this.coverPreview = null
      this.coverRemoved = false
      this.showModal = true
    },

    closeModal() {
      this.showModal = false
      this.editingBook = null
      this.coverFile = null
      this.coverPreview = null
      this.coverRemoved = false
    },

    onCoverChange(e) {
      const file = e.target.files[0]
      if (!file) return
      this.coverFile = file
      this.coverRemoved = false
      const reader = new FileReader()
      reader.onload = (ev) => { this.coverPreview = ev.target.result }
      reader.readAsDataURL(file)
    },

    clearCoverFile() {
      this.coverFile = null
      this.coverPreview = null
    },

    markRemoveCover() {
      this.coverRemoved = true
      this.coverFile = null
      this.coverPreview = null
    },

    coverColor,

    async saveBook() {
      this.formError = ''
      this.saving = true
      try {
        let savedBook
        if (this.editingBook) {
          const { data } = await axios.put(`/api/books/${this.editingBook.id}`, this.form)
          const idx = this.books.findIndex(b => b.id === data.id)
          if (idx !== -1) {
            this.books[idx] = { ...data, cover_image: this.books[idx].cover_image }
            savedBook = this.books[idx]
          }
        } else {
          const { data } = await axios.post('/api/books', this.form)
          this.books.unshift(data)
          savedBook = this.books[0]
        }

        // vāka augšupielāde vai dzēšana
        if (savedBook && this.coverFile) {
          const fd = new FormData()
          fd.append('cover', this.coverFile)
          const { data: cv } = await axios.post(`/api/books/${savedBook.id}/cover`, fd)
          savedBook.cover_image = cv.cover_image
        } else if (savedBook && this.coverRemoved && this.editingBook) {
          await axios.delete(`/api/books/${savedBook.id}/cover`)
          savedBook.cover_image = null
        }

        this.closeModal()
      } catch (err) {
        const errors = err.response?.data?.errors
        if (errors) {
          this.formError = Object.values(errors).flat()[0]
        } else {
          this.formError = 'Something went wrong. Please try again.'
        }
      } finally {
        this.saving = false
      }
    },

    confirmDelete(book) {
      this.deletingBook = book
    },

    async deleteBook() {
      this.saving = true
      try {
        await axios.delete(`/api/books/${this.deletingBook.id}`)
        this.books = this.books.filter(b => b.id !== this.deletingBook.id)
        this.deletingBook = null
      } catch (err) {
        this.flashError(err.response?.data?.message || 'Failed to delete the book. Please try again.')
      } finally {
        this.saving = false
      }
    },

    async handleLogout() {
      try {
        await axios.post('/api/logout')
      } finally {
        clearAuth()
        this.$router.push({ name: 'home' })
      }
    }
  }
}
</script>
