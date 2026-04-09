<template>
  <div class="messages-page">

    <AppNavbar />

    <div class="messages-body">

      <aside class="convo-panel" :class="{ 'hidden-mobile': showThread }">
        <div class="convo-header">
          <h2 class="convo-title">{{ t('messages.title') }}</h2>
        </div>

        <!-- support is always pinned at the top -->
        <div
          class="convo-item convo-support"
          role="button"
          tabindex="0"
          @click="$router.push({ name: 'support' })"
          @keyup.enter="$router.push({ name: 'support' })"
        >
          <div class="convo-avatar convo-avatar-support">🛟</div>
          <div class="convo-info">
            <div class="convo-name">{{ t('support.title') }}</div>
            <div class="convo-last">{{ t('messages.supportSub') }}</div>
          </div>
        </div>
        <div class="convo-divider"></div>

        <div v-if="loadingConvos" class="convo-empty">{{ t('messages.loading') }}</div>

        <div v-else-if="convosError" class="convo-empty text-danger">{{ convosError }}</div>

        <div v-else-if="conversations.length === 0" class="convo-empty">
          {{ t('messages.noConvos') }}<br>
          {{ t('messages.noConvosSub') }}
        </div>

        <div
          v-for="convo in conversations"
          :key="convo.user.id"
          class="convo-item"
          :class="{ active: activeUser?.id === convo.user.id }"
          role="button"
          tabindex="0"
          @click="openConversation(convo.user)"
          @keyup.enter="openConversation(convo.user)"
        >
          <div class="convo-avatar">{{ convo.user.name[0].toUpperCase() }}</div>
          <div class="convo-info">
            <div class="convo-name">
              {{ convo.user.name }}
              <span v-if="convo.is_blocked" class="convo-blocked-tag">{{ t('messages.blockedTag') }}</span>
            </div>
            <div class="convo-last">{{ convo.last_message?.body }}</div>
          </div>
          <div v-if="convo.unread > 0" class="convo-badge">{{ convo.unread }}</div>
        </div>
      </aside>

      <main class="thread-panel" :class="{ 'hidden-mobile': !showThread }">

        <div v-if="!activeUser" class="thread-empty">
          <span class="thread-empty-icon">💬</span>
          <p>{{ t('messages.selectConvo') }}</p>
        </div>

        <template v-else>
          <div class="thread-header">
            <button class="back-btn" @click="showThread = false">{{ t('messages.back') }}</button>
            <span
              class="thread-user-link"
              role="button"
              tabindex="0"
              @click="$router.push({ name: 'userProfile', params: { id: activeUser.id } })"
              @keyup.enter="$router.push({ name: 'userProfile', params: { id: activeUser.id } })"
            >{{ activeUser.name }}</span>
            <button class="thread-block-btn" :class="{ blocked: activeUserBlocked }" @click="toggleBlock">
              {{ activeUserBlocked ? t('up.unblock') : t('up.block') }}
            </button>
          </div>

          <div class="thread-messages" ref="messageList" role="log" aria-live="polite" aria-label="Ziņojumi">
            <div v-if="loadingThread" class="thread-status">{{ t('messages.loading') }}</div>
            <div v-if="threadError" class="text-center text-danger p-3">{{ threadError }}</div>
            <div v-else-if="messages.length === 0" class="thread-status">
              {{ t('messages.noMessages') }}
            </div>

            <div
              v-for="msg in messages"
              :key="msg.id"
              class="msg-row"
              :class="{ 'msg-mine': msg.from_user_id === authStore.user.id }"
            >
              <div
                class="msg-bubble"
                :class="{ 'msg-bubble-read': msg.from_user_id === authStore.user.id && msg.read_at, 'msg-bubble-sent': msg.from_user_id === authStore.user.id && !msg.read_at }"
                @contextmenu.prevent="msg.from_user_id === authStore.user.id && !msg.read_at && editingId !== msg.id ? openCtxMenu(msg, $event) : null"
                @touchstart="msg.from_user_id === authStore.user.id && !msg.read_at && editingId !== msg.id ? startLongPress(msg, $event) : null"
                @touchend="cancelLongPress"
                @touchcancel="cancelLongPress"
              >
                <template v-if="editingId === msg.id">
                  <textarea
                    v-model="editBody"
                    class="msg-edit-input"
                    rows="2"
                    @keydown.enter.exact.prevent="saveEdit(msg)"
                    @keydown.escape="cancelEdit"
                  ></textarea>
                  <div class="msg-edit-actions">
                    <button class="msg-edit-save" @click="saveEdit(msg)" :disabled="editSaving">{{ editSaving ? '…' : t('messages.save') }}</button>
                    <button class="msg-edit-cancel" @click="cancelEdit">{{ t('messages.cancel') }}</button>
                  </div>
                </template>

                <template v-else>
                  <p class="msg-body">{{ msg.body }}</p>
                  <div class="msg-meta">
                    <span v-if="msg.edited_at" class="msg-edited">{{ t('messages.edited') }}</span>
                    <span class="msg-time">{{ formatTime(msg.created_at) }}</span>
                  </div>
                </template>
              </div>
            </div>

            <!-- right-click / long-press menu -->
            <div v-if="ctxMenu.visible" class="msg-ctx-menu" :style="{ top: ctxMenu.y + 'px', left: ctxMenu.x + 'px' }">
              <button @click="startEdit(ctxMenu.msg); ctxMenu.visible = false">{{ t('messages.editBtn') }}</button>
              <button @click="unsendMessage(ctxMenu.msg); ctxMenu.visible = false" class="msg-ctx-danger">{{ t('messages.unsendBtn') }}</button>
            </div>
          </div>

          <div v-if="activeUserBlocked" class="thread-blocked-notice">{{ t('msg.blocked') }}</div>

          <div v-else class="thread-compose">
            <textarea
              v-model="compose"
              class="compose-input"
              :placeholder="t('messages.placeholder')"
              aria-label="Rakstīt ziņu"
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
              aria-label="Send"
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
import langMixin from '../langMixin.js'
import AppNavbar from './AppNavbar.vue'

export default {
  name: 'Messages',

  components: { AppNavbar },

  mixins: [langMixin],

  data() {
    return {
      authStore,
      conversations: [],
      activeUser: null,
      messages: [],
      compose: '',
      loadingConvos: true,
      loadingThread: false,
      sending: false,
      showThread: false,
      pollTimer: null,
      convosError: null,
      threadError: null,
      activeUserBlocked: false,
      editingId: null,
      editBody: '',
      editSaving: false,
      ctxMenu: { visible: false, x: 0, y: 0, msg: null },
      longPressTimer: null,
    }
  },

  async mounted() {
    document.addEventListener('click', this.closeCtxMenu)
    await this.fetchConversations()

    // if we came from a book card or profile page, open that convo right away
    if (this.$route.query.userId) {
      const userId   = Number(this.$route.query.userId)
      const userName = this.$route.query.userName || 'User'
      await this.openConversation({ id: userId, name: userName })
    }
  },

  beforeUnmount() {
    clearInterval(this.pollTimer)
    document.removeEventListener('click', this.closeCtxMenu)
  },

  methods: {
    async fetchConversations() {
      this.loadingConvos = true
      this.convosError = null
      try {
        const { data } = await axios.get('/api/messages')
        this.conversations = data
      } catch (err) {
        this.convosError = err.response?.data?.message || 'Failed to load conversations.'
      } finally {
        this.loadingConvos = false
      }
    },

    async openConversation(user) {
      this.activeUser        = user
      this.activeUserBlocked = false
      this.showThread        = true
      clearInterval(this.pollTimer)

      // check block status before showing the thread
      try {
        const { data } = await axios.get(`/api/users/${user.id}`)
        this.activeUserBlocked = (data.is_blocked || data.they_blocked_me) ?? false
      } catch { /* ignore */ }

      await this.fetchThread()

      // poll every 3s while this convo is open
      this.pollTimer = setInterval(() => {
        if (this.activeUser) this.pollNewMessages()
      }, 3000)
    },

    async toggleBlock() {
      try {
        if (this.activeUserBlocked) {
          await axios.delete(`/api/blocks/${this.activeUser.id}`)
          this.activeUserBlocked = false
        } else {
          await axios.post(`/api/blocks/${this.activeUser.id}`)
          this.activeUserBlocked = true
        }
        // sync the blocked tag in the sidebar too
        const convo = this.conversations.find(c => c.user.id === this.activeUser.id)
        if (convo) convo.is_blocked = this.activeUserBlocked
      } catch { /* ignore */ }
    },

    async fetchThread() {
      this.loadingThread = true
      this.threadError = null
      try {
        const { data } = await axios.get(`/api/messages/${this.activeUser.id}`)
        this.messages = data

        const convo = this.conversations.find(c => c.user.id === this.activeUser.id)
        if (convo) convo.unread = 0

        await this.$nextTick()
        this.scrollToBottom()
      } catch (err) {
        this.threadError = err.response?.data?.message || 'Failed to load messages.'
      } finally {
        this.loadingThread = false
      }
    },

    async pollNewMessages() {
      if (!this.activeUser) return
      const lastId = this.messages.at(-1)?.id ?? 0
      try {
        const { data } = await axios.get(`/api/messages/${this.activeUser.id}`)
        const fresh = data.filter(m => m.id > lastId)
        if (fresh.length === 0) return
        this.messages.push(...fresh)
        const convo = this.conversations.find(c => c.user.id === this.activeUser.id)
        if (convo) {
          convo.last_message = { body: fresh.at(-1).body }
          convo.unread = 0
        }
        await this.$nextTick()
        this.scrollToBottom()
      } catch { /* silent */ }
    },

    async sendMessage() {
      const body = this.compose.trim()
      if (!body || this.sending) return

      this.sending = true
      try {
        const { data } = await axios.post('/api/messages', {
          to_user_id: this.activeUser.id,
          body,
        })
        this.messages.push(data)
        this.compose = ''
        this.$nextTick(() => {
          if (this.$refs.composeInput) {
            this.$refs.composeInput.style.height = 'auto'
          }
        })

        // update or create the sidebar entry
        const convo = this.conversations.find(c => c.user.id === this.activeUser.id)
        if (convo) {
          convo.last_message = { body, created_at: data.created_at }
        } else {
          this.conversations.unshift({
            user:         this.activeUser,
            last_message: { body, created_at: data.created_at },
            unread:       0,
          })
        }

        await this.$nextTick()
        this.scrollToBottom()
      } catch (err) {
        alert(err.response?.data?.message || 'Failed to send message.')
      } finally {
        this.sending = false
      }
    },

    scrollToBottom() {
      const el = this.$refs.messageList
      if (el) el.scrollTop = el.scrollHeight
    },

    autoResize(e) {
      const el = e.target
      el.style.height = 'auto'
      el.style.height = Math.min(el.scrollHeight, 120) + 'px'
    },

    openCtxMenu(msg, e) {
      this.positionCtxMenu(msg, e.currentTarget)
    },

    positionCtxMenu(msg, bubble) {
      const r = bubble.getBoundingClientRect()
      const menuW = 140
      const menuH = 88
      const gap = 6

      // own messages sit on the right, so try placing the menu to the left first
      let x, y

      if (r.left - menuW - gap >= 0) {
        x = r.left - menuW - gap
        y = r.top
      } else if (r.right + menuW + gap <= window.innerWidth) {
        // no room left, go right
        x = r.right + gap
        y = r.top
      } else {
        // last resort: below the bubble
        x = Math.max(8, r.right - menuW)
        y = r.bottom + gap
      }

      // don't let it go off the bottom of the screen
      if (y + menuH > window.innerHeight) y = r.top - menuH - gap

      this.ctxMenu = { visible: true, x, y, msg }
    },

    closeCtxMenu() {
      this.ctxMenu.visible = false
    },

    startLongPress(msg, e) {
      this.longPressTimer = setTimeout(() => {
        this.positionCtxMenu(msg, e.currentTarget)
      }, 500)
    },

    cancelLongPress() {
      clearTimeout(this.longPressTimer)
    },

    startEdit(msg) {
      this.editingId = msg.id
      this.editBody  = msg.body
    },

    cancelEdit() {
      this.editingId = null
      this.editBody  = ''
    },

    async saveEdit(msg) {
      const body = this.editBody.trim()
      if (!body || this.editSaving) return
      this.editSaving = true
      try {
        const { data } = await axios.put(`/api/messages/${msg.id}`, { body })
        const idx = this.messages.findIndex(m => m.id === msg.id)
        if (idx !== -1) this.messages[idx] = data
        this.cancelEdit()
      } catch (err) {
        alert(err.response?.data?.message || 'Could not edit message.')
      } finally {
        this.editSaving = false
      }
    },

    async unsendMessage(msg) {
      if (!confirm(this.t('messages.unsendConfirm'))) return
      try {
        await axios.delete(`/api/messages/${msg.id}`)
        this.messages = this.messages.filter(m => m.id !== msg.id)
      } catch (err) {
        alert(err.response?.data?.message || 'Could not unsend message.')
      }
    },

    formatTime(dateStr) {
      return new Date(dateStr).toLocaleString('en-GB', {
        day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
      })
    },

  },
}
</script>
