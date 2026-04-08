<template>
  <div class="page-layout">
  <AppNavbar />
  <div class="admin-page container-xl py-4">
    <h1 class="admin-title mb-4">{{ t('admin.title') }}</h1>

    <div v-if="error" class="alert alert-danger">{{ error }}</div>

    <!-- Users -->
    <div class="admin-card mb-4">
      <h2 class="admin-section-title admin-section-toggle mb-0" @click="toggle('users')" :aria-expanded="!collapsed.users">
        {{ t('admin.users') }}
        <span class="toggle-arrow" :class="{ rotated: !collapsed.users }">▶</span>
      </h2>
      <div v-show="!collapsed.users" class="mt-3 table-responsive">
        <table class="table table-hover align-middle" :aria-label="t('admin.users')">
          <thead>
            <tr>
              <th scope="col">{{ t('admin.col.name') }}</th>
              <th scope="col">{{ t('admin.col.email') }}</th>
              <th scope="col">{{ t('admin.col.books') }}</th>
              <th scope="col">{{ t('admin.col.joined') }}</th>
              <th scope="col">{{ t('admin.col.role') }}</th>
              <th scope="col">{{ t('admin.col.status') }}</th>
              <th scope="col">{{ t('admin.col.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in users" :key="u.id">
              <td>{{ u.name }}</td>
              <td>{{ u.email }}</td>
              <td>{{ u.books_count }}</td>
              <td>{{ u.joined }}</td>
              <td>
                <span :class="u.is_admin ? 'badge bg-success' : 'badge bg-secondary'">
                  {{ u.is_admin ? t('admin.role.admin') : t('admin.role.user') }}
                </span>
              </td>
              <td>
                <span :class="u.is_blocked ? 'badge bg-danger' : 'badge bg-light text-dark'">
                  {{ u.is_blocked ? t('admin.status.blocked') : t('admin.status.active') }}
                </span>
              </td>
              <td>
                <div class="d-flex gap-2 flex-wrap">
                  <template v-if="u.id !== currentUserId">
                    <button v-if="!u.is_blocked && !u.is_admin"
                      class="btn btn-sm btn-outline-danger"
                      @click="blockUser(u)">{{ t('admin.btn.block') }}</button>
                    <button v-if="u.is_blocked"
                      class="btn btn-sm btn-outline-success"
                      @click="unblockUser(u)">{{ t('admin.btn.unblock') }}</button>
                    <button v-if="!u.is_admin"
                      class="btn btn-sm btn-outline-primary"
                      @click="makeAdmin(u)">{{ t('admin.btn.makeAdmin') }}</button>
                    <button v-if="u.is_admin"
                      class="btn btn-sm btn-outline-warning"
                      @click="removeAdmin(u)">{{ t('admin.btn.removeAdmin') }}</button>
                  </template>
                  <span v-else class="text-muted small">{{ t('admin.status.you') }}</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Books -->
    <div class="admin-card mb-4">
      <h2 class="admin-section-title admin-section-toggle mb-0" @click="toggle('books')" :aria-expanded="!collapsed.books">
        {{ t('admin.books') }}
        <span class="toggle-arrow" :class="{ rotated: !collapsed.books }">▶</span>
      </h2>
      <div v-show="!collapsed.books" class="mt-3 table-responsive">
        <table class="table table-hover align-middle" :aria-label="t('admin.books')">
          <thead>
            <tr>
              <th scope="col">{{ t('admin.col.title') }}</th>
              <th scope="col">{{ t('admin.col.author') }}</th>
              <th scope="col">{{ t('admin.col.genre') }}</th>
              <th scope="col">{{ t('admin.col.status') }}</th>
              <th scope="col">{{ t('admin.col.owner') }}</th>
              <th scope="col">{{ t('admin.col.added') }}</th>
              <th scope="col">{{ t('admin.col.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in books" :key="b.id">
              <td>{{ b.title }}</td>
              <td>{{ b.author }}</td>
              <td>{{ b.genre }}</td>
              <td>
                <span :class="{
                  'badge bg-success': b.status === 'Available',
                  'badge bg-warning text-dark': b.status === 'Pending',
                  'badge bg-secondary': b.status === 'Exchanged',
                }">{{ b.status }}</span>
              </td>
              <td>{{ b.owner }}</td>
              <td>{{ b.created_at }}</td>
              <td>
                <button class="btn btn-sm btn-outline-danger" @click="deleteBook(b)">{{ t('admin.btn.delete') }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Swap Requests -->
    <div class="admin-card mb-4">
      <h2 class="admin-section-title admin-section-toggle mb-0" @click="toggle('swaps')" :aria-expanded="!collapsed.swaps">
        {{ t('admin.swaps') }}
        <span class="toggle-arrow" :class="{ rotated: !collapsed.swaps }">▶</span>
      </h2>
      <div v-show="!collapsed.swaps" class="mt-3 table-responsive">
        <table class="table table-hover align-middle" :aria-label="t('admin.swaps')">
          <thead>
            <tr>
              <th scope="col">{{ t('admin.col.requester') }}</th>
              <th scope="col">{{ t('admin.col.offers') }}</th>
              <th scope="col">{{ t('admin.col.wants') }}</th>
              <th scope="col">{{ t('admin.col.status') }}</th>
              <th scope="col">{{ t('admin.col.date') }}</th>
              <th scope="col">{{ t('admin.col.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in swaps" :key="s.id">
              <td>{{ s.requester?.name }}</td>
              <td>{{ s.offered_book?.title }}</td>
              <td>{{ s.wanted_book?.title }}</td>
              <td>
                <span :class="{
                  'badge bg-warning text-dark': s.status === 'pending',
                  'badge bg-success': s.status === 'accepted',
                  'badge bg-secondary': s.status === 'declined',
                }">{{ s.status }}</span>
              </td>
              <td>{{ s.created_at?.slice(0, 10) }}</td>
              <td>
                <div class="d-flex gap-2 flex-wrap">
                  <button v-if="s.status === 'pending'"
                    class="btn btn-sm btn-outline-success"
                    @click="acceptSwap(s)">{{ t('admin.btn.accept') }}</button>
                  <button v-if="s.status === 'pending'"
                    class="btn btn-sm btn-outline-secondary"
                    @click="declineSwap(s)">{{ t('admin.btn.decline') }}</button>
                  <button class="btn btn-sm btn-outline-danger"
                    @click="deleteSwap(s)">{{ t('admin.btn.delete') }}</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Ratings -->
    <div class="admin-card">
      <h2 class="admin-section-title admin-section-toggle mb-0" @click="toggle('ratings')" :aria-expanded="!collapsed.ratings">
        {{ t('admin.ratings') }}
        <span class="toggle-arrow" :class="{ rotated: !collapsed.ratings }">▶</span>
      </h2>
      <div v-show="!collapsed.ratings" class="mt-3 table-responsive">
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
            <tr v-for="r in ratings" :key="r.id">
              <td>{{ r.book }}</td>
              <td>{{ r.author }}</td>
              <td>{{ r.rater }}</td>
              <td>
                <span class="stars-display">{{ '★'.repeat(r.stars) }}{{ '☆'.repeat(5 - r.stars) }}</span>
              </td>
              <td>{{ r.review || '—' }}</td>
              <td>{{ r.date }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <AppFooter />
  </div>
</template>

<script>
import axios from 'axios'
import { authStore } from '../authStore.js'
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
      error: '',
      collapsed: { users: false, books: true, swaps: true, ratings: true },
    }
  },

  computed: {
    currentUserId() {
      return authStore.user?.id
    }
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
        const [u, b, s, r] = await Promise.all([
          axios.get('/api/admin/users'),
          axios.get('/api/admin/books'),
          axios.get('/api/admin/swaps'),
          axios.get('/api/admin/ratings'),
        ])
        this.users = u.data
        this.books = b.data
        this.swaps = s.data
        this.ratings = r.data
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

    async deleteBook(b) {
      if (!confirm(this.t('admin.confirm.deleteBook').replace('{title}', b.title))) return
      await axios.delete(`/api/admin/books/${b.id}`)
      this.books = this.books.filter(x => x.id !== b.id)
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
  }
}
</script>

<style scoped>
.admin-page { max-width: 1200px; }

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

.table th { font-weight: 600; font-size: .85rem; text-transform: uppercase; letter-spacing: .04em; }

.stars-display { color: #f5a623; letter-spacing: .1em; }

.admin-section-toggle {
  cursor: pointer;
  user-select: none;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: .5rem .75rem;
  margin: -.5rem -.75rem;
  border-radius: 8px;
  transition: background .15s;
}

.admin-section-toggle:hover { background: rgba(0,0,0,.05); }

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
</style>

<style>
/* admin dark mode — not scoped so [data-theme] selector works */
[data-theme="dark"] .admin-card {
  background: #211d18;
  box-shadow: 0 2px 8px rgba(0,0,0,.3);
}

[data-theme="dark"] .admin-section-toggle:hover { background: rgba(255,255,255,.06); }

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
