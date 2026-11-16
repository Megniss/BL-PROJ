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

          <div class="thread-messages" ref="msgList">
            <div v-if="loadingThread" class="thread-status">{{ t('support.loading') }}</div>
            <div v-else-if="active.messages?.length === 0" class="thread-status">{{ t('support.noMessages') }}</div>

            <div
              v-for="msg in active.messages"
              :key="msg.id"
              class="msg-row"
              :class="{ 'msg-mine': admin === msg.from_admin }"
            >
              <div class="msg-bubble" :class="{ 'msg-bubble-sent': admin === msg.from_admin }">
                <img
                  v-if="msg.image_url"
                  :src="msg.image_url"
                  class="msg-image"
                  :alt="t('messages.photo')"
                  loading="lazy"
                  @click="lightbox = msg.image_url"
                  @load="scrollBottom"
                  @error="refreshImage(msg)"
                />
                <p v-else-if="msg.image_removed" class="msg-image-removed">{{ t('messages.photoRemoved') }}</p>
                <p v-if="msg.body" class="msg-body">{{ msg.body }}</p>
                <div class="msg-meta">
                  <template v-if="msg.from_admin">
                    <span class="support-admin-tag" :data-name="msg.sender.name">{{ t('support.adminTag') }}</span>
                    <span v-if="admin" class="support-admin-name">{{ msg.sender.name }}</span>
                  </template>
                  <span class="msg-time">{{ formatTime(msg.created_at) }}</span>
                </div>
              </div>
            </div>
          </div>

          <div v-if="active.status === 'closed'" class="thread-blocked-notice">
            {{ t('support.closedNotice') }}
          </div>
          <template v-else>
            <!-- bilde pirms sūtīšanas -->
            <div v-if="pendingPreview || imageError" class="compose-image-preview">
              <div v-if="pendingPreview" class="compose-image-thumb">
                <img :src="pendingPreview" :alt="t('messages.photo')" />
                <button class="compose-image-remove" @click="clearImage" :aria-label="t('messages.removePhoto')">✕</button>
              </div>
              <span v-if="imageError" class="text-danger small">{{ imageError }}</span>
            </div>

            <div class="thread-compose">
              <input ref="imageInput" type="file" accept="image/*" class="d-none" @change="onImagePicked" />
              <button
                class="compose-attach-btn"
                :disabled="sending || preparingImage"
                @click="$refs.imageInput.click()"
                :aria-label="t('messages.addPhoto')"
                :title="t('messages.addPhoto')"
              >📎</button>
              <textarea
                v-model="compose"
                class="compose-input"
                :placeholder="t('messages.placeholder')"
                rows="1"
                @keydown.enter.exact.prevent="sendMessage"
                @input="autoResize"
                @paste="onPaste"
                ref="composeInput"
              ></textarea>
              <button
                class="compose-send-btn"
                :disabled="!canSend"
                :class="{ active: canSend }"
                @click="sendMessage"
                aria-label="Send"
              >{{ sending || preparingImage ? '…' : '➤' }}</button>
            </div>
          </template>
        </template>
      </main>
    </div>

    <Teleport to="body">
      <div v-if="lightbox" class="msg-lightbox" @click="lightbox = null">
        <img :src="lightbox" :alt="t('messages.photo')" />
      </div>
    </Teleport>
  </div>
</template>

<script>
import axios from 'axios'
import { authStore } from '../authStore.js'
import AppNavbar from './AppNavbar.vue'
import langMixin from '../langMixin.js'
import { resizeImage } from '../imageResize.js'

export default {
  name: 'Support',
  components: { AppNavbar },
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
      compose: '',
      sending: false,
      pollTimer: null,
      pendingImage: null,
      pendingPreview: null,
      preparingImage: false,
      imageError: '',
      lightbox: null,
    }
  },

  async mounted() {
    await this.loadList()

    // ?new=1 nozīmē uzreiz atvērt jauna pieteikuma formu
    if (this.$route.query.new) {
      this.showNew = true
    }
  },

  computed: {
    api() {
      return this.admin ? '/api/admin/complaints' : '/api/complaints'
    },
    canSend() {
      return (this.compose.trim() || this.pendingImage) && !this.sending && !this.preparingImage
    },
  },

  beforeUnmount() {
    clearInterval(this.pollTimer)
    this.clearImage()
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

    async open(c) {
      clearInterval(this.pollTimer)
      this.showThread = true
      this.loadingThread = true
      this.active = { ...c, messages: [] }
      this.clearImage()
      this.imageError = ''
      try {
        const { data } = await axios.get(`${this.api}/${c.id}`)
        this.active = data
        // atsvaidzina priekšskatījuma tekstu sānbārā
        const idx = this.complaints.findIndex(x => x.id === c.id)
        if (idx !== -1) {
          this.complaints[idx].last_msg = data.messages?.at(-1)?.body ?? this.complaints[idx].last_msg
          // admins atvēra - vairs nav jauns
          if (this.admin && this.complaints[idx].unread) {
            this.complaints[idx].unread = false
            this.$emit('read')
          }
        }
        this.$nextTick(() => this.scrollBottom())
      } finally {
        this.loadingThread = false
      }

      // reizi 4 sekundēs pārbauda jaunas ziņas
      this.pollTimer = setInterval(() => {
        if (this.active) this.pollMessages()
      }, 4000)
    },

    async pollMessages() {
      const lastId = this.active.messages?.at(-1)?.id ?? 0
      try {
        const { data } = await axios.get(`${this.api}/${this.active.id}`)
        const fresh = (data.messages ?? []).filter(m => m.id > lastId)
        if (fresh.length) {
          this.active.messages.push(...fresh)
          const idx = this.complaints.findIndex(x => x.id === this.active.id)
          if (idx !== -1) {
            this.complaints[idx].last_msg = fresh.at(-1).body
            this.complaints[idx].last_image = !!fresh.at(-1).image_url
          }
          this.$nextTick(() => this.scrollBottom())
        }
        // sinhronizē statusu ja admins aizvēra
        if (data.status !== this.active.status) {
          this.active.status = data.status
          const idx = this.complaints.findIndex(x => x.id === this.active.id)
          if (idx !== -1) this.complaints[idx].status = data.status
        }
      } catch { /* klusums */ }
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
      if (!this.canSend) return
      this.sending = true
      const body = this.compose.trim()
      const image = this.pendingImage
      this.compose = ''

      // bilde iet kā FormData, tāpat kā parastajā čatā
      const form = new FormData()
      if (body) form.append('body', body)
      if (image) form.append('image', image)

      try {
        const { data } = await axios.post(`${this.api}/${this.active.id}/messages`, form)
        this.active.messages.push(data)
        this.clearImage()
        // sānbāra priekšskatījums sinhronā
        const idx = this.complaints.findIndex(x => x.id === this.active.id)
        if (idx !== -1) {
          this.complaints[idx].last_msg = body
          this.complaints[idx].last_image = !!image
        }
        this.$nextTick(() => this.scrollBottom())
      } catch (err) {
        this.compose = body // atdod atpakaļ lai lietotājs nezaudē ziņu
        if (image) this.imageError = err.response?.data?.message || this.t('messages.photoError')
      } finally {
        this.sending = false
      }
    },

    async attachImage(file) {
      this.imageError = ''
      if (!file.type.startsWith('image/')) return

      this.preparingImage = true
      try {
        const resized = await resizeImage(file)
        if (resized.size > 5 * 1024 * 1024) throw new Error('too big')
        this.clearImage()
        this.pendingImage = resized
        this.pendingPreview = URL.createObjectURL(resized)
      } catch {
        this.imageError = this.t('messages.photoError')
      } finally {
        this.preparingImage = false
      }
    },

    onImagePicked(e) {
      const file = e.target.files?.[0]
      e.target.value = ''
      if (file) this.attachImage(file)
    },

    // Ctrl+V ar bildi
    onPaste(e) {
      const item = [...(e.clipboardData?.items ?? [])].find(i => i.type.startsWith('image/'))
      if (!item) return
      e.preventDefault()
      this.attachImage(item.getAsFile())
    },

    clearImage() {
      if (this.pendingPreview) URL.revokeObjectURL(this.pendingPreview)
      this.pendingImage = null
      this.pendingPreview = null
    },

    // saite novecojusi - paņem jaunu
    async refreshImage(msg) {
      if (msg.imageRetried || !this.active) return
      msg.imageRetried = true
      try {
        const { data } = await axios.get(`${this.api}/${this.active.id}`)
        const fresh = data.messages?.find(m => m.id === msg.id)
        if (fresh?.image_url) msg.image_url = fresh.image_url
      } catch { /* ignorē */ }
    },

    async closeComplaint() {
      await axios.patch(`${this.api}/${this.active.id}/close`)
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

.support-admin-tag {
  font-size: .65rem;
  font-weight: 700;
  color: var(--color-primary, #3a6b3e);
  text-transform: uppercase;
  letter-spacing: .04em;
  margin-right: .3rem;
  position: relative;
  cursor: default;
}
/* uzbraucot rāda admina vārdu */
.support-admin-tag[data-name]:hover::after {
  content: attr(data-name);
  position: absolute;
  bottom: calc(100% + 4px);
  left: 50%;
  transform: translateX(-50%);
  background: #1f2937;
  color: #fff;
  font-size: .7rem;
  font-weight: 500;
  padding: .2rem .5rem;
  border-radius: 5px;
  white-space: nowrap;
  pointer-events: none;
  text-transform: none;
  letter-spacing: 0;
}
.support-admin-name {
  font-size: .65rem;
  font-weight: 600;
  background: var(--color-primary, #3a6b3e);
  color: #fff;
  border-radius: 4px;
  padding: .05rem .35rem;
  margin-right: .3rem;
}
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
