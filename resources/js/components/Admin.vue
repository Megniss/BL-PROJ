<template>
  <div class="page-layout">
  <AppNavbar />
  <div class="admin-page container-xl py-4">
    <h1 class="admin-title mb-4">{{ t('admin.title') }}</h1>

    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <div class="admin-card mb-4">
      <h2 class="admin-section-title mb-0">
        {{ t('admin.users') }}
        <button class="toggle-arrow" :class="{ rotated: !collapsed.users }" @click="toggle('users')" :aria-expanded="!collapsed.users" :aria-label="t('admin.users')">▶</button>
      </h2>
      <div v-show="!collapsed.users" class="mt-3">
        <div class="filter-bar mb-3">
          <input v-model="filters.users.search" type="text" class="form-control form-control-sm" :placeholder="t('admin.filter.search')" />
          <select v-model="filters.users.role" class="form-select form-select-sm">
            <option value="">{{ t('admin.filter.allRoles') }}</option>
            <option value="admin">{{ t('admin.role.admin') }}</option>
            <option value="user">{{ t('admin.role.user') }}</option>
          </select>
          <select v-model="filters.users.status" class="form-select form-select-sm">
            <option value="">{{ t('admin.filter.allStatuses') }}</option>
            <option value="active">{{ t('admin.status.active') }}</option>
            <option value="blocked">{{ t('admin.status.blocked') }}</option>
          </select>
        </div>
        <div class="table-responsive">
        <table class="table table-hover align-middle" :aria-label="t('admin.users')">
          <thead>
            <tr>
              <th scope="col">{{ t('admin.col.name') }}</th>
              <th scope="col">{{ t('admin.col.email') }}</th>
              <th scope="col" class="text-center">{{ t('admin.col.books') }}</th>
              <th scope="col">{{ t('admin.col.joined') }}</th>
              <th scope="col">{{ t('admin.col.role') }}</th>
              <th scope="col">{{ t('admin.col.status') }}</th>
              <th scope="col" class="ab-col"></th>
              <th scope="col" class="ab-col"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in filteredUsers" :key="u.id">
              <td class="fw-semibold">{{ u.name }}</td>
              <td class="text-muted">{{ u.email }}</td>
              <td class="text-center">{{ u.books_count }}</td>
              <td class="text-nowrap">{{ u.joined }}</td>
              <td>
                <span class="stag" :class="u.is_admin ? 'stag-purple' : 'stag-gray'" :title="u.is_admin ? t('admin.role.admin') : t('admin.role.user')">
                  {{ u.is_admin ? 'A' : 'U' }}
                </span>
              </td>
              <td>
                <span class="stag" :class="u.is_blocked ? 'stag-red' : 'stag-green'" :title="u.is_blocked ? t('admin.status.blocked') : t('admin.status.active')">
                  {{ u.is_blocked ? '✕' : '✓' }}
                </span>
              </td>
              <template v-if="u.id !== currentUserId">
                <td class="ab-col">
                  <button v-if="!u.is_blocked && !u.is_admin" class="ab btn btn-sm btn-outline-danger" @click="blockUser(u)">{{ t('admin.btn.block') }}</button>
                  <button v-if="u.is_blocked" class="ab btn btn-sm btn-outline-success" @click="unblockUser(u)">{{ t('admin.btn.unblock') }}</button>
                </td>
                <td class="ab-col">
                  <button v-if="!u.is_admin" class="ab btn btn-sm btn-outline-primary" @click="makeAdmin(u)">{{ t('admin.btn.makeAdmin') }}</button>
                  <button v-if="u.is_admin" class="ab btn btn-sm btn-outline-warning" @click="removeAdmin(u)">{{ t('admin.btn.removeAdmin') }}</button>
                </td>
              </template>
              <template v-else>
                <td class="ab-col"></td>
                <td class="ab-col"><span class="text-muted small">{{ t('admin.status.you') }}</span></td>
              </template>
            </tr>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <div class="admin-card mb-4">
      <h2 class="admin-section-title mb-0">
        {{ t('admin.books') }}
        <button class="toggle-arrow" :class="{ rotated: !collapsed.books }" @click="toggle('books')" :aria-expanded="!collapsed.books" :aria-label="t('admin.books')">▶</button>
      </h2>
      <div v-show="!collapsed.books" class="mt-3">
        <div class="filter-bar mb-3">
          <input v-model="filters.books.search" type="text" class="form-control form-control-sm" :placeholder="t('admin.filter.searchBooks')" />
          <select v-model="filters.books.status" class="form-select form-select-sm">
            <option value="">{{ t('admin.filter.allStatuses') }}</option>
            <option value="Available">{{ t('books.status.Available') }}</option>
            <option value="Pending">{{ t('books.status.Pending') }}</option>
            <option value="UnderReview">{{ t('books.status.UnderReview') }}</option>
            <option value="Exchanged">{{ t('books.status.Exchanged') }}</option>
          </select>
        </div>
        <div class="table-responsive">
        <table class="table table-hover align-middle" :aria-label="t('admin.books')">
          <thead>
            <tr>
              <th scope="col">{{ t('admin.col.title') }}</th>
              <th scope="col">{{ t('admin.col.author') }}</th>
              <th scope="col">{{ t('admin.col.genre') }}</th>
              <th scope="col">{{ t('admin.col.owner') }}</th>
              <th scope="col">{{ t('admin.col.status') }}</th>
              <th scope="col">{{ t('admin.col.added') }}</th>
              <th scope="col" class="ab-col"></th>
              <th scope="col" class="ab-col"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in filteredBooks" :key="b.id">
              <td class="fw-semibold">{{ b.title }}</td>
              <td>{{ b.author }}</td>
              <td>{{ b.genre }}</td>
              <td>{{ b.owner }}</td>
              <td>
                <span class="stag" :class="{
                  'stag-green':  b.status === 'Available',
                  'stag-yellow': b.status === 'Pending',
                  'stag-gray':   b.status === 'Exchanged',
                  'stag-blue':   b.status === 'UnderReview',
                }" :title="t('books.status.' + b.status) || b.status">
                  {{ { Available: '✓', Pending: '~', Exchanged: '⇄', UnderReview: '!' }[b.status] ?? '?' }}
                </span>
              </td>
              <td class="text-nowrap">{{ b.created_at }}</td>
              <td class="ab-col">
                <button v-if="b.status !== 'UnderReview'" class="ab btn btn-sm btn-outline-info" @click="reviewBook(b)">{{ t('admin.btn.review') }}</button>
                <button v-if="b.status === 'UnderReview'" class="ab btn btn-sm btn-outline-success" @click="unreviewBook(b)">{{ t('admin.btn.unreview') }}</button>
              </td>
              <td class="ab-col">
                <button class="ab btn btn-sm btn-outline-danger" @click="deleteBook(b)">{{ t('admin.btn.delete') }}</button>
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <div class="admin-card mb-4">
      <h2 class="admin-section-title mb-0">
        {{ t('admin.swaps') }}
        <button class="toggle-arrow" :class="{ rotated: !collapsed.swaps }" @click="toggle('swaps')" :aria-expanded="!collapsed.swaps" :aria-label="t('admin.swaps')">▶</button>
      </h2>
      <div v-show="!collapsed.swaps" class="mt-3">
        <div class="filter-bar mb-3">
          <input v-model="filters.swaps.search" type="text" class="form-control form-control-sm" :placeholder="t('admin.filter.searchSwaps')" />
          <select v-model="filters.swaps.status" class="form-select form-select-sm">
            <option value="">{{ t('admin.filter.allStatuses') }}</option>
            <option value="pending">{{ t('admin.status.pending') }}</option>
            <option value="accepted">{{ t('admin.status.accepted') }}</option>
            <option value="declined">{{ t('admin.status.declined') }}</option>
          </select>
        </div>
        <div class="table-responsive">
        <table class="table table-hover align-middle" :aria-label="t('admin.swaps')">
          <thead>
            <tr>
              <th scope="col">{{ t('admin.col.requester') }}</th>
              <th scope="col">{{ t('admin.col.offers') }}</th>
              <th scope="col">{{ t('admin.col.wants') }}</th>
              <th scope="col">{{ t('admin.col.status') }}</th>
              <th scope="col">{{ t('admin.col.date') }}</th>
              <th scope="col" class="ab-col"></th>
              <th scope="col" class="ab-col"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in filteredSwaps" :key="s.id">
              <td class="fw-semibold">{{ s.requester?.name }}</td>
              <td>{{ s.offered_book?.title }}</td>
              <td>{{ s.wanted_book?.title }}</td>
              <td>
                <span class="stag" :class="{
                  'stag-yellow': s.status === 'pending',
                  'stag-green':  s.status === 'accepted',
                  'stag-gray':   s.status === 'declined',
                }" :title="t('admin.status.' + s.status)">
                  {{ { pending: '~', accepted: '✓', declined: '✕' }[s.status] ?? '?' }}
                </span>
              </td>
              <td class="text-nowrap">{{ s.created_at?.slice(0, 10) }}</td>
              <td class="ab-col">
                <button v-if="s.status === 'pending'" class="ab btn btn-sm btn-outline-success" @click="acceptSwap(s)">{{ t('admin.btn.accept') }}</button>
                <button v-if="s.status === 'pending'" class="ab btn btn-sm btn-outline-warning" @click="declineSwap(s)">{{ t('admin.btn.decline') }}</button>
              </td>
              <td class="ab-col">
                <button class="ab btn btn-sm btn-outline-danger" @click="deleteSwap(s)">{{ t('admin.btn.delete') }}</button>
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <div class="admin-card">
      <h2 class="admin-section-title mb-0">
        {{ t('admin.ratings') }}
        <button class="toggle-arrow" :class="{ rotated: !collapsed.ratings }" @click="toggle('ratings')" :aria-expanded="!collapsed.ratings" :aria-label="t('admin.ratings')">▶</button>
      </h2>
      <div v-show="!collapsed.ratings" class="mt-3">
        <div class="filter-bar mb-3">
          <input v-model="filters.ratings.search" type="text" class="form-control form-control-sm" :placeholder="t('admin.filter.searchBookRater')" />
          <select v-model="filters.ratings.stars" class="form-select form-select-sm">
            <option value="">{{ t('admin.filter.allStars') }}</option>
            <option value="1">★</option>
            <option value="2">★★</option>
            <option value="3">★★★</option>
            <option value="4">★★★★</option>
            <option value="5">★★★★★</option>
          </select>
        </div>
        <div class="table-responsive">
        <table class="table table-hover align-middle" :aria-label="t('admin.ratings')">
          <thead>
            <tr>
              <th scope="col">{{ t('admin.col.title') }}</th>
              <th scope="col">{{ t('admin.col.author') }}</th>
              <th scope="col">{{ t('admin.col.ratedBy') }}</th>
              <th scope="col">{{ t('admin.col.stars') }}</th>
              <th scope="col">{{ t('admin.col.review') }}</th>
              <th scope="col">{{ t('admin.col.date') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in filteredRatings" :key="r.id">
              <td class="fw-semibold">{{ r.book }}</td>
              <td>{{ r.author }}</td>
              <td>{{ r.rater }}</td>
              <td>
                <span class="stars-display">{{ '★'.repeat(r.stars) }}{{ '☆'.repeat(5 - r.stars) }}</span>
              </td>
              <td>{{ r.review || '—' }}</td>
              <td class="text-nowrap">{{ r.date }}</td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <div class="admin-card mt-4">
      <h2 class="admin-section-title mb-0">
        {{ t('admin.languages') }}
        <button class="toggle-arrow" :class="{ rotated: !collapsed.langs }" @click="toggle('langs')" :aria-expanded="!collapsed.langs" :aria-label="t('admin.languages')">▶</button>
      </h2>
      <div v-show="!collapsed.langs" class="mt-3">

        <!-- lang list -->
        <table class="table table-hover align-middle mb-3">
          <thead>
            <tr>
              <th>{{ t('admin.col.flag') }}</th>
              <th>{{ t('admin.col.code') }}</th>
              <th>{{ t('admin.col.name') }}</th>
              <th>{{ t('admin.col.status') }}</th>
              <th>{{ t('admin.col.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="lang in allLanguages" :key="lang.code">
              <template v-if="editingLangRow === lang.code">
                <td>
                  <input v-model="langRowEdit.flag" class="form-control form-control-sm" style="width:70px" placeholder="ru" />
                  <span v-if="langRowEdit.flag" :class="'fi fi-' + langRowEdit.flag.toLowerCase()" style="font-size:1.2rem;border-radius:2px;margin-top:3px;display:block"></span>
                </td>
                <td><code>{{ lang.code }}</code></td>
                <td><input v-model="langRowEdit.name" class="form-control form-control-sm" style="width:130px" /></td>
                <td>
                  <span class="status-dot" :class="lang.is_active ? 'dot-active' : 'dot-muted'" :title="lang.is_active ? t('admin.status.active') : t('admin.status.inactive')"></span>
                </td>
                <td>
                  <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-sm btn-primary" @click="saveLangRow(lang)">{{ t('admin.btn.save') }}</button>
                    <button class="btn btn-sm btn-outline-secondary" @click="editingLangRow = null">{{ t('modal.cancel') }}</button>
                  </div>
                </td>
              </template>
              <template v-else>
                <td>
                  <span v-if="lang.flag" :class="'fi fi-' + lang.flag" style="font-size:1.4rem;border-radius:2px"></span>
                  <span v-else style="font-size:0.85rem;font-weight:600">{{ lang.code.toUpperCase() }}</span>
                </td>
                <td><code>{{ lang.code }}</code></td>
                <td>{{ lang.name }}</td>
                <td>
                  <span class="status-dot" :class="lang.is_active ? 'dot-active' : 'dot-muted'" :title="lang.is_active ? t('admin.status.active') : t('admin.status.inactive')"></span>
                </td>
                <td>
                  <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-sm btn-outline-secondary" @click="startEditLangRow(lang)">{{ t('admin.btn.edit') }}</button>
                    <button class="btn btn-sm btn-outline-primary" @click="openLangEditor(lang)">
                      {{ t('admin.btn.editTranslations') }}
                    </button>
                    <button v-if="lang.is_active && lang.code !== 'en'" class="btn btn-sm btn-outline-danger" @click="deactivateLang(lang)">
                      {{ t('admin.btn.removeLang') }}
                    </button>
                    <button v-if="!lang.is_active" class="btn btn-sm btn-outline-success" @click="reactivateLang(lang)">
                      {{ t('admin.btn.restoreLang') }}
                    </button>
                  </div>
                </td>
              </template>
            </tr>
          </tbody>
        </table>

        <!-- add new lang form toggle -->
        <div v-if="!addingLang">
          <button class="btn btn-sm btn-outline-primary" @click="addingLang = true">{{ t('admin.btn.addLang') }}</button>
        </div>
        <div v-else class="d-flex gap-2 flex-wrap align-items-end">
          <div>
            <label class="form-label small mb-1">{{ t('admin.lang.flag') }}</label>
            <input v-model="newLang.flag" class="form-control form-control-sm" style="width:80px" placeholder="ru" />
            <div class="form-text" style="font-size:0.7rem">{{ t('admin.lang.flagHint') }}</div>
            <span v-if="newLang.flag" :class="'fi fi-' + newLang.flag.toLowerCase()" style="font-size:1.4rem;border-radius:2px;margin-top:4px;display:block"></span>
          </div>
          <div>
            <label class="form-label small mb-1">{{ t('admin.lang.code') }}</label>
            <input v-model="newLang.code" class="form-control form-control-sm" style="width:80px" placeholder="de" />
          </div>
          <div>
            <label class="form-label small mb-1">{{ t('admin.lang.name') }}</label>
            <input v-model="newLang.name" class="form-control form-control-sm" style="width:140px" placeholder="German" />
          </div>
          <button class="btn btn-sm btn-primary" @click="addLanguage">{{ t('admin.btn.save') }}</button>
          <button class="btn btn-sm btn-outline-secondary" @click="addingLang = false">{{ t('modal.cancel') }}</button>
        </div>

        <!-- translation editor modal -->
        <div v-if="editingLang" class="lang-editor-overlay" @click.self="editingLang = null">
          <div class="lang-editor-modal">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h3 class="mb-0">{{ t('admin.lang.editTitle') }}: <code>{{ editingLang }}</code></h3>
              <button class="btn btn-sm btn-outline-secondary" @click="editingLang = null">✕</button>
            </div>
            <p class="text-muted small mb-3">{{ t('admin.lang.editHint') }}</p>
            <div class="lang-editor-list">
              <div v-for="(defaultVal, key) in allTranslationKeys" :key="key" class="lang-editor-row">
                <div class="lang-editor-key">{{ key }}</div>
                <input
                  class="form-control form-control-sm lang-editor-input"
                  :placeholder="defaultVal"
                  :value="langTranslations[key] ?? ''"
                  @input="langTranslations[key] = $event.target.value"
                />
              </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
              <button class="btn btn-outline-secondary btn-sm" @click="editingLang = null">{{ t('modal.cancel') }}</button>
              <button class="btn btn-primary btn-sm" :disabled="langSaving" @click="saveLangTranslations">
                {{ langSaving ? '…' : t('admin.btn.save') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="admin-card mt-4">
      <h2 class="admin-section-title mb-0">
        {{ t('admin.logs') }}
        <button class="toggle-arrow" :class="{ rotated: !collapsed.logs }" @click="toggle('logs')" :aria-expanded="!collapsed.logs" :aria-label="t('admin.logs')">▶</button>
      </h2>
      <div v-show="!collapsed.logs" class="mt-3">
        <div class="filter-bar mb-3">
          <input v-model="filters.logs.search" type="text" class="form-control form-control-sm" :placeholder="t('admin.filter.search')" />
          <select v-model="filters.logs.action" class="form-select form-select-sm">
            <option value="">{{ t('admin.filter.allActions') }}</option>
            <option value="block_user">{{ t('admin.log.block_user') }}</option>
            <option value="unblock_user">{{ t('admin.log.unblock_user') }}</option>
            <option value="make_admin">{{ t('admin.log.make_admin') }}</option>
            <option value="remove_admin">{{ t('admin.log.remove_admin') }}</option>
            <option value="delete_book">{{ t('admin.log.delete_book') }}</option>
            <option value="review_book">{{ t('admin.log.review_book') }}</option>
            <option value="unreview_book">{{ t('admin.log.unreview_book') }}</option>
            <option value="accept_swap">{{ t('admin.log.accept_swap') }}</option>
            <option value="decline_swap">{{ t('admin.log.decline_swap') }}</option>
            <option value="delete_swap">{{ t('admin.log.delete_swap') }}</option>
            <option value="support_reply">{{ t('admin.log.support_reply') }}</option>
            <option value="close_complaint">{{ t('admin.log.close_complaint') }}</option>
          </select>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle" :aria-label="t('admin.logs')">
            <thead>
              <tr>
                <th scope="col">{{ t('admin.col.date') }}</th>
                <th scope="col">{{ t('admin.col.admin') }}</th>
                <th scope="col">{{ t('admin.col.action') }}</th>
                <th scope="col">{{ t('admin.col.target') }}</th>
                <th scope="col">{{ t('admin.col.reason') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="filteredLogs.length === 0">
                <td colspan="5" class="text-center text-muted py-3">{{ t('admin.logs.empty') }}</td>
              </tr>
              <tr v-for="l in filteredLogs" :key="l.id">
                <td class="text-nowrap">{{ l.date }}</td>
                <td>{{ l.admin }}</td>
                <td><span class="log-action-badge">{{ t('admin.log.' + l.action) }}</span></td>
                <td>{{ l.target_name }}</td>
                <td>{{ l.reason || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
  <AppFooter />
  </div>
</template>

<script>
import axios from 'axios'
import { authStore } from '../authStore.js'
import { invalidateOverrides, setLocale } from '../langStore.js'
import { translations } from '../translations.js'
import AppNavbar from './AppNavbar.vue'
import AppFooter from './AppFooter.vue'
import langMixin from '../langMixin.js'

export default {
  name: 'AdminPanel',
  components: { AppNavbar, AppFooter },
  mixins: [langMixin],

  data() {
    return {
      users: [],
      books: [],
      swaps: [],
      ratings: [],
      logs: [],
      allLanguages: [],
      editingLang: null,       // which lang is open in the editor
      langTranslations: {},    // key -> translated value
      langSaving: false,
      newLang: { code: '', name: '', flag: '' },
      addingLang: false,
      editingLangRow: null,     // which row is in inline edit mode
      langRowEdit: { flag: '', name: '' },
      error: '',
      collapsed: { users: false, books: true, swaps: true, ratings: true, langs: true, logs: true },
      filters: {
        users:   { search: '', role: '', status: '' },
        books:   { search: '', status: '' },
        swaps:   { search: '', status: '' },
        ratings: { search: '', stars: '' },
        logs:    { search: '', action: '' },
      },
    }
  },

  computed: {
    currentUserId() {
      return authStore.user?.id
    },

    filteredUsers() {
      let list = this.users
      const s = this.filters.users.search.toLowerCase()
      if (s) list = list.filter(u => u.name.toLowerCase().includes(s) || u.email.toLowerCase().includes(s))
      if (this.filters.users.role === 'admin') list = list.filter(u => u.is_admin)
      if (this.filters.users.role === 'user')  list = list.filter(u => !u.is_admin)
      if (this.filters.users.status === 'active')  list = list.filter(u => !u.is_blocked)
      if (this.filters.users.status === 'blocked') list = list.filter(u => u.is_blocked)
      return list
    },

    filteredBooks() {
      let list = this.books
      const s = this.filters.books.search.toLowerCase()
      if (s) list = list.filter(b =>
        b.title?.toLowerCase().includes(s) ||
        b.author?.toLowerCase().includes(s) ||
        b.owner?.toLowerCase().includes(s) ||
        b.genre?.toLowerCase().includes(s)
      )
      if (this.filters.books.status) list = list.filter(b => b.status === this.filters.books.status)
      return list
    },

    filteredSwaps() {
      let list = this.swaps
      const s = this.filters.swaps.search.toLowerCase()
      if (s) list = list.filter(sw =>
        sw.requester?.name?.toLowerCase().includes(s) ||
        sw.offered_book?.title?.toLowerCase().includes(s) ||
        sw.wanted_book?.title?.toLowerCase().includes(s)
      )
      if (this.filters.swaps.status) list = list.filter(sw => sw.status === this.filters.swaps.status)
      return list
    },

    filteredRatings() {
      let list = this.ratings
      const s = this.filters.ratings.search.toLowerCase()
      if (s) list = list.filter(r => r.book?.toLowerCase().includes(s) || r.rater?.toLowerCase().includes(s))
      if (this.filters.ratings.stars) list = list.filter(r => r.stars === Number(this.filters.ratings.stars))
      return list
    },

    allTranslationKeys() {
      // use EN as the master list of keys + fallback values
      return translations['en'] ?? {}
    },

    filteredLogs() {
      let list = this.logs
      const s = this.filters.logs.search.toLowerCase()
      if (s) list = list.filter(l => l.admin?.toLowerCase().includes(s) || l.target_name?.toLowerCase().includes(s))
      if (this.filters.logs.action) list = list.filter(l => l.action === this.filters.logs.action)
      return list
    },
  },

  async mounted() {
    await this.load()
  },

  methods: {
    toggle(section) {
      this.collapsed[section] = !this.collapsed[section]
    },

    async load() {
      try {
        const [u, b, s, r, l, langs] = await Promise.all([
          axios.get('/api/admin/users'),
          axios.get('/api/admin/books'),
          axios.get('/api/admin/swaps'),
          axios.get('/api/admin/ratings'),
          axios.get('/api/admin/logs'),
          axios.get('/api/admin/languages'),
        ])
        this.users = u.data
        this.books = b.data
        this.swaps = s.data
        this.ratings = r.data
        this.logs = l.data
        this.allLanguages = langs.data
      } catch {
        this.error = this.t('admin.loadError')
      }
    },

    async blockUser(u) {
      if (!confirm(this.t('admin.confirm.block').replace('{name}', u.name))) return
      await axios.patch(`/api/admin/users/${u.id}/block`)
      u.is_blocked = true
    },

    async unblockUser(u) {
      await axios.patch(`/api/admin/users/${u.id}/unblock`)
      u.is_blocked = false
    },

    async makeAdmin(u) {
      if (!confirm(this.t('admin.confirm.makeAdmin').replace('{name}', u.name))) return
      await axios.patch(`/api/admin/users/${u.id}/make-admin`)
      u.is_admin = true
      u.is_blocked = false
    },

    async removeAdmin(u) {
      if (!confirm(this.t('admin.confirm.removeAdmin').replace('{name}', u.name))) return
      await axios.patch(`/api/admin/users/${u.id}/remove-admin`)
      u.is_admin = false
    },

    async reviewBook(b) {
      const reason = prompt(this.t('admin.prompt.reviewReason').replace('{title}', b.title))
      if (reason === null) return
      if (!reason.trim()) {
        alert(this.t('admin.prompt.reviewReasonRequired'))
        return
      }
      try {
        await axios.patch(`/api/admin/books/${b.id}/review`, { reason: reason.trim() })
        b.status = 'UnderReview'
      } catch {
        this.error = this.t('admin.loadError')
      }
    },

    async unreviewBook(b) {
      try {
        await axios.patch(`/api/admin/books/${b.id}/unreview`)
        b.status = 'Available'
      } catch {
        this.error = this.t('admin.loadError')
      }
    },

    async deleteBook(b) {
      const reason = prompt(this.t('admin.prompt.deleteReason').replace('{title}', b.title))
      if (reason === null) return
      if (!reason.trim()) {
        alert(this.t('admin.prompt.deleteReasonRequired'))
        return
      }
      try {
        await axios.delete(`/api/admin/books/${b.id}`, { data: { reason: reason.trim() } })
        this.books = this.books.filter(x => x.id !== b.id)
      } catch {
        this.error = this.t('admin.loadError')
      }
    },

    async acceptSwap(s) {
      if (!confirm(this.t('admin.confirm.acceptSwap').replace('{a}', s.offered_book?.title).replace('{b}', s.wanted_book?.title))) return
      const res = await axios.patch(`/api/admin/swaps/${s.id}/accept`)
      s.status = res.data.status
    },

    async declineSwap(s) {
      if (!confirm(this.t('admin.confirm.declineSwap').replace('{a}', s.offered_book?.title).replace('{b}', s.wanted_book?.title))) return
      const res = await axios.patch(`/api/admin/swaps/${s.id}/decline`)
      s.status = res.data.status
    },

    async deleteSwap(s) {
      if (!confirm(this.t('admin.confirm.deleteSwap'))) return
      await axios.delete(`/api/admin/swaps/${s.id}`)
      this.swaps = this.swaps.filter(x => x.id !== s.id)
    },

    startEditLangRow(lang) {
      this.editingLangRow = lang.code
      this.langRowEdit = { flag: lang.flag, name: lang.name }
    },

    async saveLangRow(lang) {
      if (!this.langRowEdit.flag || !this.langRowEdit.name) return
      try {
        const { data } = await axios.patch(`/api/admin/languages/${lang.code}`, this.langRowEdit)
        lang.flag = data.flag
        lang.name = data.name
        // also update the navbar switcher if it's there
        const entry = this.langStore.languages.find(l => l.code === lang.code)
        if (entry) { entry.flag = data.flag; entry.name = data.name }
        this.editingLangRow = null
      } catch (e) {
        alert(e.response?.data?.message || 'Failed to save.')
      }
    },

    async addLanguage() {
      if (!this.newLang.code || !this.newLang.name || !this.newLang.flag) return
      try {
        const { data } = await axios.post('/api/admin/languages', this.newLang)
        this.allLanguages.push(data)
        this.langStore.languages.push({ code: data.code, name: data.name, flag: data.flag })
        this.newLang = { code: '', name: '', flag: '' }
        this.addingLang = false
      } catch (e) {
        alert(e.response?.data?.message || 'Failed to add language.')
      }
    },

    async deactivateLang(lang) {
      if (!confirm(this.t('admin.confirm.deactivateLang').replace('{name}', lang.name))) return
      await axios.patch(`/api/admin/languages/${lang.code}/deactivate`)
      lang.is_active = false
      // pull it out of the navbar switcher too
      const idx = this.langStore.languages.findIndex(l => l.code === lang.code)
      if (idx !== -1) this.langStore.languages.splice(idx, 1)
      // if the user was on that lang, fall back to EN
      if (this.langStore.locale === lang.code) setLocale('en')
    },

    async reactivateLang(lang) {
      await axios.patch(`/api/admin/languages/${lang.code}/reactivate`)
      lang.is_active = true
      if (!this.langStore.languages.find(l => l.code === lang.code)) {
        this.langStore.languages.push({ code: lang.code, name: lang.name, flag: lang.flag })
      }
    },

    async openLangEditor(lang) {
      this.editingLang = lang.code
      // start with the static file, new langs just use EN as base
      const staticBase = { ...(translations[lang.code] ?? translations['en']) }
      const { data } = await axios.get(`/api/admin/translations/${lang.code}`)
      // db overrides take priority over the static file
      this.langTranslations = { ...staticBase, ...data }
    },

    async saveLangTranslations() {
      this.langSaving = true
      try {
        await axios.post(`/api/admin/translations/${this.editingLang}`, { overrides: this.langTranslations })
        invalidateOverrides(this.editingLang)
        this.editingLang = null
      } finally {
        this.langSaving = false
      }
    },
  }
}
</script>

<style scoped>
.admin-page { max-width: 1200px; }

.filter-bar {
  display: flex;
  gap: .5rem;
  flex-wrap: wrap;
}
.filter-bar .form-control-sm,
.filter-bar .form-select-sm {
  max-width: 220px;
}

.admin-title {
  font-size: 1.6rem;
  font-weight: 700;
  color: var(--color-primary, #3a6b3e);
}

.admin-card {
  background: var(--card-bg, #fff);
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 2px 8px rgba(0,0,0,.07);
}

.admin-section-title {
  font-size: 1.1rem;
  font-weight: 600;
}

.table th { font-weight: 600; font-size: .85rem; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }

/* fixed-width action columns */
.ab-col { width: 1%; white-space: nowrap; padding-left: .35rem; padding-right: .35rem; }
.ab { min-width: 72px; white-space: nowrap; font-size: .72rem; padding: .2rem .45rem; }

/* status tag chips */
.stag {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 22px;
  border-radius: 5px;
  font-size: .75rem;
  font-weight: 700;
  line-height: 1;
  cursor: default;
}
.stag-green  { background: #d1fae5; color: #166534; }
.stag-red    { background: #fee2e2; color: #991b1b; }
.stag-yellow { background: #fef9c3; color: #854d0e; }
.stag-gray   { background: #f1f5f9; color: #64748b; }
.stag-blue   { background: #dbeafe; color: #1e40af; }
.stag-purple { background: #ede9fe; color: #5b21b6; }

.stars-display { color: #f5a623; letter-spacing: .1em; }

.admin-section-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.toggle-arrow:hover { background: #2d5630; }

.toggle-arrow {
  font-size: .7rem;
  width: 24px;
  height: 24px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--color-primary, #3a6b3e);
  color: #fff;
  transition: transform .2s ease;
  flex-shrink: 0;
}

.toggle-arrow.rotated { transform: rotate(90deg); }

.lang-editor-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.45);
  z-index: 1000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.lang-editor-modal {
  background: var(--card-bg, #fff);
  border-radius: 12px;
  padding: 1.5rem;
  width: 100%;
  max-width: 760px;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 8px 32px rgba(0,0,0,.2);
}
.lang-editor-list {
  overflow-y: auto;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: .4rem;
}
.lang-editor-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .5rem;
  align-items: center;
}
.lang-editor-key {
  font-size: .75rem;
  font-family: monospace;
  color: var(--muted, #888);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.lang-editor-input { font-size: .8rem; }

[data-theme="dark"] .lang-editor-modal { background: #211d18; }

.log-action-badge {
  font-size: .75rem;
  font-weight: 600;
  background: var(--green-light, #e8f5e9);
  color: var(--green, #3a6b3e);
  padding: .15rem .5rem;
  border-radius: 999px;
  white-space: nowrap;
}
</style>

<style>
/* dark mode — has to be unscoped so [data-theme] actually reaches */
[data-theme="dark"] .admin-card {
  background: #211d18;
  box-shadow: 0 2px 8px rgba(0,0,0,.3);
}


[data-theme="dark"] .admin-card .table {
  --bs-table-color: #e8e0d5;
  --bs-table-bg: transparent;
  --bs-table-border-color: #2e2820;
  --bs-table-hover-bg: #2a2420;
  --bs-table-hover-color: #e8e0d5;
  --bs-table-striped-color: #e8e0d5;
  color: #e8e0d5 !important;
}

[data-theme="dark"] .admin-card .table th {
  color: #9a9088 !important;
  border-color: #2e2820 !important;
}

[data-theme="dark"] .admin-card .table td {
  color: #e8e0d5 !important;
  border-color: #2e2820 !important;
}

[data-theme="dark"] .admin-card .badge.bg-light {
  background: #2e2820 !important;
  color: #c8c0b5 !important;
}
</style>
