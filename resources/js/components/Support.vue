<template>
  <div class="messages-page" :class="{ 'support-embedded': admin }">
    <AppNavbar v-if="!admin" />

    <div class="messages-body">

      <aside class="convo-panel" :class="{ 'hidden-mobile': showThread }">
        <div class="convo-header">
          <h2 class="convo-title">{{ t('support.title') }}</h2>
          <button v-if="!admin" class="btn btn-sm btn-primary" @click="showNew = !showNew">
            {{ showNew ? t('support.cancel') : t('support.newBtn') }}
          </button>
        </div>

        <div v-if="showNew" class="support-new-form">
          <!-- iemesls no saraksta, var arī meklēt rakstot -->
          <label for="support-reason" class="support-label">{{ t('support.reason') }}</label>
          <div class="reason-pick mb-2">
            <input
              id="support-reason"
              ref="reasonInput"
              v-model="reasonSearch"
              class="form-control form-control-sm"
              type="text"
              autocomplete="off"
              role="combobox"
              aria-autocomplete="list"
              aria-controls="reason-list"
              :aria-expanded="reasonOpen"
              :placeholder="t('support.pickReason')"
              @focus="openReasons"
              @click="openReasons"
              @input="reasonOpen = true; reasonActive = 0"
              @blur="closeReasons"
              @keydown.down.prevent="moveReason(1)"
              @keydown.up.prevent="moveReason(-1)"
              @keydown.enter.prevent="pickReason(filteredReasons[reasonActive])"
              @keydown.esc="reasonOpen = false"
              data-testid="support-reason"
            />
            <span class="reason-caret" aria-hidden="true">▾</span>
            <ul v-if="reasonOpen" id="reason-list" class="reason-list" role="listbox">
              <li
                v-for="(r, i) in filteredReasons"
                :key="r.key"
                role="option"
                class="reason-item"
                :class="{ active: i === reasonActive, chosen: r.key === newCategory }"
                :aria-selected="r.key === newCategory"
                @mousedown.prevent="pickReason(r)"
                @mouseenter="reasonActive = i"
              >
                <span class="reason-icon" aria-hidden="true">{{ r.icon }}</span>
                <span class="reason-text">
                  <span class="reason-name">{{ t('support.cat.' + r.key) }}</span>
                  <span class="reason-hint">{{ t('support.hint.' + r.key) }}</span>
                </span>
              </li>
              <li v-if="!filteredReasons.length" class="reason-none">{{ t('support.noReason') }}</li>
            </ul>
          </div>

          <!-- savs temats tikai pie "Cits" -->
          <input v-if="newCategory === 'other'" v-model="newSubject" class="form-control form-control-sm mb-2" :placeholder="t('support.subjectPlaceholder')" :aria-label="t('support.subjectPlaceholder')" maxlength="200" />
          <textarea v-model="newBody" class="form-control form-control-sm mb-2" :placeholder="newCategory ? t('support.ask.' + newCategory) : t('support.messagePlaceholder')" :aria-label="t('support.messagePlaceholder')" rows="3" maxlength="2000"></textarea>
          <button class="btn btn-sm btn-primary w-100" :disabled="!canCreate || creating" @click="createComplaint">
            {{ creating ? t('support.sending') : t('support.send') }}
          </button>
          <div v-if="formError" class="text-danger small mt-1">{{ formError }}</div>
        </div>

        <!-- adminam filtrs pēc iemesla -->
        <div v-if="admin && complaints.length" class="support-filter">
          <select v-model="catFilter" class="form-select form-select-sm" :aria-label="t('support.reason')" data-testid="support-filter">
            <option value="">{{ t('support.allReasons') }} ({{ complaints.length }})</option>
            <option v-for="r in usedReasons" :key="r.key" :value="r.key">{{ r.icon }} {{ t('support.cat.' + r.key) }} ({{ r.count }})</option>
          </select>
        </div>

        <div v-if="loading" class="convo-empty">{{ t('support.loading') }}</div>
        <div v-else-if="complaints.length === 0" class="convo-empty">{{ t('support.empty') }}</div>

        <div
          v-for="c in shownComplaints"
          :key="c.id"
          class="convo-item"
          :class="{ active: active?.id === c.id, 'support-unread': admin && c.unread }"
          role="button"
          tabindex="0"
          @click="open(c)"
          @keyup.enter="open(c)"
        >
          <div class="convo-avatar">{{ reasonIcon(c) || subjectInitial(c.subject) }}</div>
          <div class="convo-info">
            <div class="convo-name">
              {{ title(c) }}
              <span v-if="admin" class="text-muted small ms-1">({{ c.user?.name }})</span>
            </div>
            <div class="convo-last">{{ c.last_msg || (c.last_image ? '📷 ' + t('messages.photo') : '-') }}</div>
          </div>
          <span v-if="admin && c.unread" class="support-new-badge">{{ t('support.new') }}</span>
          <span class="support-status-badge" :class="c.status === 'closed' ? 'badge-closed' : 'badge-open'">
            {{ c.status === 'closed' ? t('support.closed') : t('support.open') }}
          </span>
        </div>
      </aside>

      <main class="thread-panel" :class="{ 'hidden-mobile': !showThread }">

        <div v-if="!active" class="thread-empty">
          <span class="thread-empty-icon">🛟</span>
          <p>{{ t('support.selectComplaint') }}</p>
        </div>

        <template v-else>
          <div class="thread-header">
            <button class="back-btn" @click="showThread = false">{{ t('messages.back') }}</button>
            <span class="thread-user-link">{{ title(active) }}</span>
            <span class="support-status-badge ms-2" :class="active.status === 'closed' ? 'badge-closed' : 'badge-open'">
              {{ active.status === 'closed' ? t('support.closed') : t('support.open') }}
            </span>
            <router-link v-if="admin && active.user"
              :to="{ name: 'userProfile', params: { id: active.user.id } }"
              class="support-user-link ms-2">
              {{ active.user.name }}
            </router-link>
            <button v-if="admin && active.status === 'open'"
              class="btn btn-sm btn-outline-secondary ms-auto"
              @click="closeComplaint">{{ t('support.closeBtn') }}</button>
          </div>

          <ChatThread
            ref="thread"
            :messages="threadMessages"
            :loading="loadingThread"
            :empty-text="t('support.noMessages')"
            :notice="active.status === 'closed' ? t('support.closedNotice') : ''"
            :typing="otherTyping"
            :send="sendMessage"
            :refresh-image="refreshImage"
            @typing="onTyping"
          />
        </template>
      </main>
    </div>
  </div>
</template>
<script>
import axios from 'axios'
import { authStore } from '../authStore.js'
import AppNavbar from './AppNavbar.vue'
import ChatThread from './ChatThread.vue'
import langMixin from '../langMixin.js'
import { messageForm, sendTyping, fetchTyping, addNew, POLL_MS } from '../chat.js'

// iemesli - tādi paši kā Complaint::CATEGORIES serverī
const REASONS = [
  { key: 'account', icon: '🔑' },
  { key: 'book', icon: '📕' },
  { key: 'swap', icon: '⇄' },
  { key: 'not_received', icon: '📦' },
  { key: 'user', icon: '🚩' },
  { key: 'content', icon: '⚠️' },
  { key: 'bug', icon: '🐞' },
  { key: 'suggestion', icon: '💡' },
  { key: 'other', icon: '💬' },
]

// meklēšanai - bez garumzīmēm un mazie burti, lai "apmainu" atrod "apmaiņu"
function plain(s) {
  return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
}

export default {
  name: 'Support',
  components: { AppNavbar, ChatThread },
  mixins: [langMixin],
  props: { admin: Boolean },
  // admin panelim - lai atjauno nelasīto skaitu
  emits: ['read'],

  data() {
    return {
      authStore,
      complaints: [],
      active: null,
      loading: true,
      loadingThread: false,
      showThread: false,
      showNew: false,
      newSubject: '',
      newBody: '',
      newCategory: '',
      reasonSearch: '',
      reasonOpen: false,
      reasonActive: 0,
      catFilter: '',
      creating: false,
      formError: '',
      pollTimer: null,
      otherTyping: false,
      retried: new Set(),
    }
  },

  async mounted() {
    await this.loadList()

    // ?new=1 nozīmē uzreiz atvērt jauna pieteikuma formu
    if (this.$route.query.new) {
      this.showNew = true
    }
    this.openFromQuery()
  },

  watch: {
    // paziņojums aizved uz ?ticket=ID arī tad, ja lapa jau atvērta
    '$route.query.ticket'() {
      this.openFromQuery()
    },
  },

  computed: {
    // ja ierakstīts tieši izvēlētais nosaukums - rāda visus
    filteredReasons() {
      const q = plain(this.reasonSearch.trim())
      if (!q || (this.newCategory && this.reasonSearch === this.t('support.cat.' + this.newCategory))) return REASONS
      return REASONS.filter(r =>
        plain(this.t('support.cat.' + r.key)).includes(q) || plain(this.t('support.hint.' + r.key)).includes(q)
      )
    },

    canCreate() {
      if (!this.newCategory || !this.newBody.trim()) return false
      return this.newCategory !== 'other' || !!this.newSubject.trim()
    },

    // admina filtram - tikai tie iemesli, kas ir sarakstā
    usedReasons() {
      return REASONS
        .map(r => ({ ...r, count: this.complaints.filter(c => (c.category || 'other') === r.key).length }))
        .filter(r => r.count > 0)
    },

    shownComplaints() {
      if (!this.catFilter) return this.complaints
      return this.complaints.filter(c => (c.category || 'other') === this.catFilter)
    },

    api() {
      return this.admin ? '/api/admin/complaints' : '/api/complaints'
    },
    // ChatThread vajag vienādu formu abiem čatiem
    threadMessages() {
      return (this.active?.messages ?? []).map(m => ({
        ...m,
        mine: this.admin === m.from_admin,
        read: false,
        tag: m.from_admin ? this.t('support.adminTag') : null,
        tagTitle: m.sender?.name,
        tagName: this.admin && m.from_admin ? m.sender?.name : null,
      }))
    },
  },

  beforeUnmount() {
    clearInterval(this.pollTimer)
  },

  methods: {
    async loadList() {
      this.loading = true
      try {
        const { data } = await axios.get(this.api)
        this.complaints = data
      } finally {
        this.loading = false
      }
    },

    // ?ticket=ID - atver šo pieteikumu (saite no zvaniņa)
    openFromQuery() {
      const id = Number(this.$route.query.ticket)
      if (!id || this.admin) return
      const c = this.listItem(id)
      if (c && this.active?.id !== id) this.open(c)
    },

    listItem(id) {
      return this.complaints.find(x => x.id === id)
    },

    async open(c) {
      clearInterval(this.pollTimer)
      this.showThread = true
      this.loadingThread = true
      this.otherTyping = false
      this.active = { ...c, messages: [] }
      this.$refs.thread?.reset()
      try {
        const { data } = await axios.get(`${this.api}/${c.id}`)
        this.active = data
        // atsvaidzina priekšskatījuma tekstu sānbārā
        const item = this.listItem(c.id)
        if (item) {
          item.last_msg = data.messages?.at(-1)?.body ?? item.last_msg
          // admins atvēra - vairs nav jauns
          if (this.admin && item.unread) {
            item.unread = false
            this.$emit('read')
          }
        }
        // lietotājam paziņojums par atbildi tagad izlasīts
        if (!this.admin) window.dispatchEvent(new Event('bookloop:notifs-refresh'))
      } finally {
        this.loadingThread = false
      }

      // reizi 3 sekundēs jaunas ziņas un vai otrs raksta
      this.pollTimer = setInterval(() => {
        if (this.active) this.poll()
      }, POLL_MS)
    },

    async poll() {
      const id = this.active.id
      const lastId = this.active.messages?.at(-1)?.id ?? 0
      try {
        const [{ data }, typing] = await Promise.all([
          axios.get(`${this.api}/${id}`),
          this.active.status === 'closed' ? false : fetchTyping(`support:${id}`),
        ])
        if (this.active?.id !== id) return

        this.otherTyping = typing

        const fresh = (data.messages ?? []).filter(m => m.id > lastId)
        if (fresh.length) {
          addNew(this.active.messages, fresh)
          this.otherTyping = false
          const item = this.listItem(id)
          if (item) {
            item.last_msg = fresh.at(-1).body
            item.last_image = !!fresh.at(-1).image_url
          }
        }
        // sinhronizē statusu ja admins aizvēra
        if (data.status !== this.active.status) {
          this.active.status = data.status
          const item = this.listItem(id)
          if (item) item.status = data.status
        }
      } catch { /* klusums */ }
    },

    onTyping() {
      if (this.active && this.active.status !== 'closed') sendTyping(`support:${this.active.id}`)
    },

    async createComplaint() {
      this.formError = ''
      this.creating = true
      try {
        const { data } = await axios.post('/api/complaints', {
          category: this.newCategory,
          subject: this.newCategory === 'other' ? this.newSubject.trim() : undefined,
          body: this.newBody.trim(),
        })
        this.complaints.unshift({ id: data.id, subject: data.subject, category: data.category, status: data.status, user: data.user, last_msg: data.messages?.[0]?.body })
        this.showNew = false
        this.newSubject = ''
        this.newBody = ''
        this.newCategory = ''
        this.reasonSearch = ''
        await this.open(data)
      } catch {
        this.formError = this.t('support.createError')
      } finally {
        this.creating = false
      }
    },

    // ChatThread izsauc, kļūda tiek parādīta tur
    async sendMessage(body, image) {
      const { data } = await axios.post(`${this.api}/${this.active.id}/messages`, messageForm(body, image))
      addNew(this.active.messages, [data])
      // sānbāra priekšskatījums sinhronā
      const item = this.listItem(this.active.id)
      if (item) {
        item.last_msg = body
        item.last_image = !!image
      }
    },

    // saite novecojusi - paņem jaunu, katrai ziņai tikai vienreiz
    async refreshImage(msg) {
      if (this.retried.has(msg.id) || !this.active) return
      this.retried.add(msg.id)
      try {
        const { data } = await axios.get(`${this.api}/${this.active.id}`)
        const fresh = data.messages?.find(m => m.id === msg.id)
        const orig = this.active.messages.find(m => m.id === msg.id)
        if (fresh?.image_url && orig) orig.image_url = fresh.image_url
      } catch { /* ignorē */ }
    },

    async closeComplaint() {
      await axios.patch(`${this.api}/${this.active.id}/close`)
      this.active.status = 'closed'
      this.otherTyping = false
      const item = this.listItem(this.active.id)
      if (item) item.status = 'closed'
    },

    openReasons() {
      this.reasonOpen = true
      const i = this.filteredReasons.findIndex(r => r.key === this.newCategory)
      this.reasonActive = Math.max(0, i)
      // iezīmē tekstu, lai rakstot uzreiz meklē no jauna
      this.$nextTick(() => this.$refs.reasonInput?.select())
    },

    // ierakstīja bet neizvēlējās - atpakaļ izvēlētais nosaukums
    closeReasons() {
      this.reasonOpen = false
      this.reasonSearch = this.newCategory ? this.t('support.cat.' + this.newCategory) : ''
    },

    moveReason(step) {
      if (!this.reasonOpen) { this.openReasons(); return }
      const n = this.filteredReasons.length
      if (n) this.reasonActive = (this.reasonActive + step + n) % n
    },

    pickReason(r) {
      if (!r) return
      this.newCategory = r.key
      this.reasonSearch = this.t('support.cat.' + r.key)
      this.reasonOpen = false
    },

    reasonIcon(c) {
      return c?.category ? REASONS.find(r => r.key === c.category)?.icon : ''
    },

    // iemeslu rāda lietotāja valodā, "cits" un vecajiem - savu tematu
    title(c) {
      if (c?.category && c.category !== 'other') return this.t('support.cat.' + c.category)
      return c?.subject
    },

    subjectInitial(subject) {
      return subject?.[0]?.toUpperCase() ?? '?'
    },
  }
}
</script>


<style scoped>
.support-new-form {
  padding: .75rem 1rem;
  border-bottom: 1px solid var(--border);
}

/* iemesla izvēle ar meklēšanu */
.support-label { display: block; font-size: .75rem; font-weight: 600; color: var(--muted); margin-bottom: .25rem; }
.reason-pick { position: relative; }
.reason-pick .form-control { padding-right: 1.8rem; cursor: pointer; }
.reason-caret { position: absolute; right: .6rem; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: .75rem; pointer-events: none; }
.reason-list {
  position: absolute; z-index: 30; left: 0; right: 0; top: calc(100% + 4px);
  list-style: none; margin: 0; padding: .25rem;
  max-height: 300px; overflow-y: auto;
  background: var(--card-bg, #fff); border: 1px solid var(--border); border-radius: 8px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
}
.reason-item { display: flex; gap: .6rem; align-items: flex-start; padding: .45rem .55rem; border-radius: 6px; cursor: pointer; }
.reason-item.active { background: var(--green-light); }
.reason-item.chosen .reason-name { color: var(--green); }
.reason-icon { width: 1.3rem; text-align: center; flex-shrink: 0; }
.reason-text { display: flex; flex-direction: column; min-width: 0; }
.reason-name { font-size: .85rem; font-weight: 600; color: var(--ink); }
.reason-hint { font-size: .72rem; color: var(--muted); line-height: 1.3; }
.reason-none { padding: .5rem; font-size: .8rem; color: var(--muted); }

.support-filter { padding: .5rem 1rem; border-bottom: 1px solid var(--border); }

.support-status-badge {
  font-size: .7rem;
  font-weight: 600;
  padding: .15rem .45rem;
  border-radius: 999px;
  white-space: nowrap;
  flex-shrink: 0;
}
.badge-open   { background: #d1fae5; color: #065f46; }

/* jauns pieteikums, ko admins vēl nav atvēris */
.support-new-badge {
  font-size: .65rem;
  font-weight: 700;
  padding: .15rem .45rem;
  border-radius: 999px;
  background: #dc2626;
  color: #fff;
  margin-right: .3rem;
  flex-shrink: 0;
}
.support-unread .convo-name { font-weight: 700; }
.badge-closed { background: #f3f4f6; color: #6b7280; }

/* admina birka tagad app.css, jo to rāda ChatThread */
.support-user-link {
  font-size: .8rem;
  font-weight: 600;
  color: var(--color-primary, #3a6b3e);
  text-decoration: none;
  border-bottom: 1px dotted currentColor;
}
.support-user-link:hover { opacity: .75; }

[data-theme="dark"] .badge-open   { background: #064e3b; color: #6ee7b7; }
[data-theme="dark"] .badge-closed { background: #2e2820; color: #9a9088; }
</style>
