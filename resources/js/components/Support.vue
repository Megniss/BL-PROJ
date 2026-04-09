<template>
  <div class="messages-page">
    <AppNavbar />

    <div class="messages-body">

      <!-- Left panel: complaint list -->
      <aside class="convo-panel" :class="{ 'hidden-mobile': showThread }">
        <div class="convo-header">
          <h2 class="convo-title">{{ t('support.title') }}</h2>
          <button v-if="!authStore.user?.is_admin" class="btn btn-sm btn-primary" @click="showNew = !showNew">
            {{ showNew ? t('support.cancel') : t('support.newBtn') }}
          </button>
        </div>

        <!-- New complaint form -->
        <div v-if="showNew" class="support-new-form">
          <input v-model="newSubject" class="form-control form-control-sm mb-2" :placeholder="t('support.subjectPlaceholder')" maxlength="200" />
          <textarea v-model="newBody" class="form-control form-control-sm mb-2" :placeholder="t('support.messagePlaceholder')" rows="3" maxlength="2000"></textarea>
          <button class="btn btn-sm btn-primary w-100" :disabled="!newSubject.trim() || !newBody.trim() || creating" @click="createComplaint">
            {{ creating ? t('support.sending') : t('support.send') }}
          </button>
          <div v-if="formError" class="text-danger small mt-1">{{ formError }}</div>
        </div>

        <div v-if="loading" class="convo-empty">{{ t('support.loading') }}</div>
        <div v-else-if="complaints.length === 0" class="convo-empty">{{ t('support.empty') }}</div>

        <div
          v-for="c in complaints"
          :key="c.id"
          class="convo-item"
          :class="{ active: active?.id === c.id }"
          role="button"
          tabindex="0"
          @click="open(c)"
          @keyup.enter="open(c)"
        >
          <div class="convo-avatar">{{ subjectInitial(c.subject) }}</div>
          <div class="convo-info">
            <div class="convo-name">
              {{ c.subject }}
              <span v-if="authStore.user?.is_admin" class="text-muted small ms-1">({{ c.user?.name }})</span>
            </div>
            <div class="convo-last">{{ c.last_msg || '—' }}</div>
          </div>
          <span class="support-status-badge" :class="c.status === 'closed' ? 'badge-closed' : 'badge-open'">
            {{ c.status === 'closed' ? t('support.closed') : t('support.open') }}
          </span>
        </div>
      </aside>

      <!-- Right panel: chat -->
      <main class="thread-panel" :class="{ 'hidden-mobile': !showThread }">

        <div v-if="!active" class="thread-empty">
          <span class="thread-empty-icon">🛟</span>
          <p>{{ t('support.selectComplaint') }}</p>
        </div>

        <template v-else>
          <div class="thread-header">
            <button class="back-btn" @click="showThread = false">{{ t('messages.back') }}</button>
            <span class="thread-user-link">{{ active.subject }}</span>
            <span class="support-status-badge ms-2" :class="active.status === 'closed' ? 'badge-closed' : 'badge-open'">
              {{ active.status === 'closed' ? t('support.closed') : t('support.open') }}
            </span>
            <button v-if="authStore.user?.is_admin && active.status === 'open'"
              class="btn btn-sm btn-outline-secondary ms-auto"
              @click="closeComplaint">{{ t('support.closeBtn') }}</button>
          </div>

          <div class="thread-messages" ref="msgList">
            <div v-if="loadingThread" class="thread-status">{{ t('support.loading') }}</div>
            <div v-else-if="active.messages?.length === 0" class="thread-status">{{ t('support.noMessages') }}</div>

            <div
              v-for="msg in active.messages"
              :key="msg.id"
              class="msg-row"
              :class="{ 'msg-mine': msg.sender?.is_admin }"
            >
              <div class="msg-bubble" :class="msg.sender?.is_admin ? 'msg-bubble-sent' : ''">
                <p class="msg-body">{{ msg.body }}</p>
                <div class="msg-meta">
                  <span v-if="msg.sender?.is_admin" class="support-admin-tag">{{ t('support.adminTag') }}</span>
                  <span class="msg-time">{{ formatTime(msg.created_at) }}</span>
                </div>
              </div>
            </div>
          </div>

          <div v-if="active.status === 'closed'" class="thread-blocked-notice">
            {{ t('support.closedNotice') }}
          </div>
          <div v-else class="thread-compose">
            <textarea
              v-model="compose"
              class="compose-input"
              :placeholder="t('messages.placeholder')"
              rows="1"
              @keydown.enter.exact.prevent="sendMessage"
              @input="autoResize"
              ref="composeInput"
            ></textarea>
            <button
              class="compose-send-btn"
              :disabled="!compose.trim() || sending"
              :class="{ active: compose.trim() }"
              @click="sendMessage"
            >{{ sending ? '…' : '➤' }}</button>
          </div>
        </template>
      </main>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import { authStore } from '../authStore.js'
import AppNavbar from './AppNavbar.vue'
import langMixin from '../langMixin.js'

export default {
  name: 'Support',
  components: { AppNavbar },
  mixins: [langMixin],

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
      creating: false,
      formError: '',
      compose: '',
      sending: false,
    }
  },

  async mounted() {
    await this.loadList()

    // open complaint from query param (e.g. /support?new=1)
    if (this.$route.query.new) {
      this.showNew = true
    }
  },

  methods: {
    async loadList() {
      this.loading = true
      try {
        const { data } = await axios.get('/api/complaints')
        this.complaints = data
      } finally {
        this.loading = false
      }
    },

    async open(c) {
      this.showThread = true
      this.loadingThread = true
      this.active = { ...c, messages: [] }
      try {
        const { data } = await axios.get(`/api/complaints/${c.id}`)
        this.active = data
        // update list entry
        const idx = this.complaints.findIndex(x => x.id === c.id)
        if (idx !== -1) this.complaints[idx].last_msg = data.messages?.at(-1)?.body ?? this.complaints[idx].last_msg
        this.$nextTick(() => this.scrollBottom())
      } finally {
        this.loadingThread = false
      }
    },

    async createComplaint() {
      this.formError = ''
      this.creating = true
      try {
        const { data } = await axios.post('/api/complaints', {
          subject: this.newSubject.trim(),
          body: this.newBody.trim(),
        })
        this.complaints.unshift({ id: data.id, subject: data.subject, status: data.status, user: data.user, last_msg: data.messages?.[0]?.body })
        this.showNew = false
        this.newSubject = ''
        this.newBody = ''
        await this.open(data)
      } catch {
        this.formError = this.t('support.createError')
      } finally {
        this.creating = false
      }
    },

    async sendMessage() {
      if (!this.compose.trim() || this.sending) return
      this.sending = true
      const body = this.compose.trim()
      this.compose = ''
      try {
        const { data } = await axios.post(`/api/complaints/${this.active.id}/messages`, { body })
        this.active.messages.push(data)
        // update list preview
        const idx = this.complaints.findIndex(x => x.id === this.active.id)
        if (idx !== -1) this.complaints[idx].last_msg = body
        this.$nextTick(() => this.scrollBottom())
      } catch {
        this.compose = body // restore if failed
      } finally {
        this.sending = false
      }
    },

    async closeComplaint() {
      await axios.patch(`/api/complaints/${this.active.id}/close`)
      this.active.status = 'closed'
      const idx = this.complaints.findIndex(x => x.id === this.active.id)
      if (idx !== -1) this.complaints[idx].status = 'closed'
    },

    scrollBottom() {
      const el = this.$refs.msgList
      if (el) el.scrollTop = el.scrollHeight
    },

    autoResize(e) {
      e.target.style.height = 'auto'
      e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px'
    },

    formatTime(ts) {
      if (!ts) return ''
      const d = new Date(ts)
      return d.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
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
  border-bottom: 1px solid var(--bs-border-color, #e5e7eb);
}

.support-status-badge {
  font-size: .7rem;
  font-weight: 600;
  padding: .15rem .45rem;
  border-radius: 999px;
  white-space: nowrap;
  flex-shrink: 0;
}
.badge-open   { background: #d1fae5; color: #065f46; }
.badge-closed { background: #f3f4f6; color: #6b7280; }

.support-admin-tag {
  font-size: .65rem;
  font-weight: 700;
  color: var(--color-primary, #3a6b3e);
  text-transform: uppercase;
  letter-spacing: .04em;
  margin-right: .3rem;
}

[data-theme="dark"] .badge-open   { background: #064e3b; color: #6ee7b7; }
[data-theme="dark"] .badge-closed { background: #2e2820; color: #9a9088; }
</style>
