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
          :class="{ active: active?.id === c.id, 'support-unread': admin && c.unread }"
          role="button"
          tabindex="0"
          @click="open(c)"
          @keyup.enter="open(c)"
        >
          <div class="convo-avatar">{{ subjectInitial(c.subject) }}</div>
          <div class="convo-info">
            <div class="convo-name">
              {{ c.subject }}
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
            <span class="thread-user-link">{{ active.subject }}</span>
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
