<template>
  <div class="notif-wrap" ref="wrap">
    <button class="notif-bell" @click="toggle" :aria-expanded="open" :aria-label="t('notif.title') + (badge ? ' (' + badge + ')' : '')" data-testid="notif-bell">
      <span aria-hidden="true">🔔</span>
      <span v-if="badge" class="notif-badge" aria-hidden="true" data-testid="notif-badge">{{ badge > 9 ? '9+' : badge }}</span>
    </button>

    <div v-if="open" class="notif-dropdown" role="dialog" :aria-label="t('notif.title')">
      <div class="notif-header">
        <span>{{ t('notif.title') }}</span>
        <div class="notif-actions">
          <!-- vienmēr redzama, pelēka ja nav ko atzīmēt -->
          <button class="notif-action" :disabled="unread === 0" @click.stop="markAllRead" data-testid="notif-read-all">
            ✓ {{ t('notif.markAllRead') }}
          </button>
          <button v-if="hasRead" class="notif-action notif-action-muted" @click.stop="clearRead" :title="t('notif.clearTitle')" data-testid="notif-clear">
            {{ t('notif.clear') }}
          </button>
        </div>
      </div>

      <div class="notif-list">
        <!-- gaidošie apmaiņas pieprasījumi ar pogām -->
        <template v-if="pending.length">
          <div class="notif-section-label">{{ t('notif.swapRequests') }}</div>
          <div v-for="req in pending" :key="'req-' + req.id" class="notif-item notif-swap unread">
            <span class="notif-icon" aria-hidden="true">⇄</span>
            <div class="notif-body">
              <p class="notif-msg">
                <strong>{{ req.requester?.name }}</strong> {{ t('notif.wants') }}
                <strong>{{ req.wanted_book?.title }}</strong> {{ t('notif.andOffers') }}
                <strong>{{ req.offered_book?.title }}</strong>.
              </p>
              <div class="notif-swap-actions">
                <button class="btn btn-primary btn-sm" :disabled="busy" @click.stop="answerSwap(req, 'accept')">{{ t('notif.accept') }}</button>
                <button class="btn btn-outline-secondary btn-sm" :disabled="busy" @click.stop="answerSwap(req, 'decline')">{{ t('notif.decline') }}</button>
              </div>
            </div>
          </div>
        </template>

        <template v-if="items.length">
          <div v-if="pending.length" class="notif-section-label">{{ t('notif.activity') }}</div>
          <button
            v-for="n in items"
            :key="n.id"
            type="button"
            class="notif-item"
            :class="{ unread: !n.read_at }"
            @click="openItem(n)"
            data-testid="notif-item"
          >
            <span class="notif-icon" aria-hidden="true">{{ icon(n.kind) }}</span>
            <span class="notif-body">
              <span class="notif-msg">{{ text(n) }}</span>
              <span v-if="sub(n)" class="notif-sub">{{ sub(n) }}</span>
              <span class="notif-time">{{ timeAgo(n.created_at) }}</span>
            </span>
            <span v-if="!n.read_at" class="notif-dot" aria-hidden="true"></span>
          </button>
        </template>

        <div v-if="!pending.length && !items.length" class="notif-empty">
          {{ loading ? t('dash.loading') : t('notif.empty') }}
        </div>
      </div>

      <div v-if="nextPage" class="notif-load-more">
        <button class="notif-load-more-btn" @click.stop="loadMore">{{ t('notif.loadMore') }}</button>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import langMixin from '../langMixin.js'
import { showToast } from '../toast.js'

// zvaniņš visās lapās (AppNavbar). jaunos paziņojumus pārbauda ik 30 s,
// atverot cilni un pēc lapas maiņas. citas lapas var izsaukt atjaunošanu ar
// window.dispatchEvent(new Event('bookloop:notifs-refresh'))
const POLL_MS = 30000

export default {
  name: 'NotificationBell',
  mixins: [langMixin],

  data() {
    return {
      open: false,
      items: [],
      pending: [],
      unread: 0,
      nextPage: null,
      loading: true,
      busy: false,
      timer: null,
    }
  },

  computed: {
    badge() {
      return this.unread + this.pending.length
    },
    hasRead() {
      return this.items.some(n => n.read_at)
    },
  },

  mounted() {
    this.refresh()
    this.timer = setInterval(() => {
      // fonā esošā cilne serveri nemoka
      if (!document.hidden) this.refresh()
    }, POLL_MS)
    document.addEventListener('visibilitychange', this.onVisible)
    document.addEventListener('click', this.onDocClick)
    document.addEventListener('keydown', this.onKey)
    window.addEventListener('bookloop:notifs-refresh', this.refresh)
  },

  beforeUnmount() {
    clearInterval(this.timer)
    document.removeEventListener('visibilitychange', this.onVisible)
    document.removeEventListener('click', this.onDocClick)
    document.removeEventListener('keydown', this.onKey)
    window.removeEventListener('bookloop:notifs-refresh', this.refresh)
  },

  watch: {
    $route() {
      this.open = false
      this.refresh()
    },
  },

  methods: {
    async refresh() {
      try {
        const { data } = await axios.get('/api/notifications')
        this.items = data.data
        this.nextPage = data.next_page_url
        this.unread = data.unread_count
        this.pending = data.pending_swaps
      } catch (e) {
        // izlogojies - vairs nepārbaudām
        if (e.response?.status === 401) clearInterval(this.timer)
      } finally {
        this.loading = false
      }
    },

    async loadMore() {
      if (!this.nextPage) return
      try {
        const { data } = await axios.get(this.nextPage)
        const have = new Set(this.items.map(n => n.id))
        this.items.push(...data.data.filter(n => !have.has(n.id)))
        this.nextPage = data.next_page_url
      } catch { /* ignorē */ }
    },

    toggle() {
      this.open = !this.open
      if (this.open) this.refresh()
    },

    onVisible() {
      if (!document.hidden) this.refresh()
    },

    onDocClick(e) {
      if (this.open && this.$refs.wrap && !this.$refs.wrap.contains(e.target)) this.open = false
    },

    onKey(e) {
      if (e.key === 'Escape') this.open = false
    },

    // uzspiežot - izlasīts un aizved uz vietu, par ko ir paziņojums
    async openItem(n) {
      if (!n.read_at) {
        n.read_at = new Date().toISOString()
        this.unread = Math.max(0, this.unread - 1)
        axios.patch(`/api/notifications/${n.id}/read`).catch(() => {})
      }
      this.open = false
      if (n.link && this.$route.fullPath !== n.link) this.$router.push(n.link)
    },

    async markAllRead() {
      try {
        await axios.post('/api/notifications/read-all')
        const now = new Date().toISOString()
        this.items.forEach(n => { n.read_at = n.read_at || now })
        this.unread = 0
      } catch { /* ignorē */ }
    },

    // izdzēš izlasītos, nelasītie un apmaiņas pieprasījumi paliek
    async clearRead() {
      try {
        await axios.delete('/api/notifications/read')
        await this.refresh()
      } catch {
        showToast(this.t('dash.genericError'), 'error')
      }
    },

    async answerSwap(req, action) {
      this.busy = true
      try {
        await axios.patch(`/api/swap-requests/${req.id}/${action}`)
        this.pending = this.pending.filter(r => r.id !== req.id)
        // My Library lapai jāatjauno grāmatas un pieprasījumi
        window.dispatchEvent(new Event('bookloop:swaps-changed'))
      } catch (err) {
        showToast(err.response?.data?.message || this.t('dash.genericError'), 'error')
      } finally {
        this.busy = false
      }
    },

    icon(kind) {
      return {
        message: '✉️',
        swap_accepted: '✅',
        swap_declined: '↩️',
        swap_unavailable: '⚠️',
        book_review: '🔍',
        book_deleted: '🗑️',
        support_reply: '💬',
      }[kind] || '🔔'
    },

    // teksts lietotāja valodā. vecajiem paziņojumiem bez vajadzīgajiem laukiem - saglabātais angļu teksts
    text(n) {
      const d = n.data || {}
      const params = {
        message: { name: d.sender_name },
        swap_accepted: { book: d.wanted_book },
        swap_declined: { book: d.offered_book },
        swap_unavailable: { book: d.book },
        book_review: { book: d.book },
        book_deleted: { book: d.book },
        support_reply: { subject: d.subject },
      }[n.kind]

      if (!params || Object.values(params).some(v => !v)) return d.message || ''

      let s = this.t('notif.k.' + n.kind)
      for (const [k, v] of Object.entries(params)) s = s.replace('{' + k + '}', v)
      return s
    },

    // otrā rinda: ziņas sākums vai admina iemesls
    sub(n) {
      const d = n.data || {}
      if (n.kind === 'message' && d.preview) return '"' + d.preview + '"'
      if ((n.kind === 'book_review' || n.kind === 'book_deleted') && d.reason) return this.t('notif.reason') + ': ' + d.reason
      return ''
    },

    timeAgo(dateStr) {
      const diff = (Date.now() - new Date(dateStr)) / 1000
      if (diff < 60) return this.t('notif.justNow')
      if (diff < 3600) return this.t('notif.minAgo').replace('{n}', Math.floor(diff / 60))
      if (diff < 86400) return this.t('notif.hAgo').replace('{n}', Math.floor(diff / 3600))
      if (diff < 172800) return this.t('notif.yesterday')
      return new Date(dateStr).toLocaleDateString(this.langStore?.locale === 'lv' ? 'lv-LV' : 'en-GB', {
        day: 'numeric', month: 'short', year: 'numeric',
      })
    },
  },
}
</script>
