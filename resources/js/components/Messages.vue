<template>
  <div class="messages-page">

    <!-- Navigācija -->
    <AppNavbar />

    <!-- Saturs -->
    <div class="messages-body">

      <!-- Sarakstes saraksts -->
      <aside class="convo-panel" :class="{ 'hidden-mobile': showThread }">
        <div class="convo-header">
          <h2 class="convo-title">Messages</h2>
        </div>

        <div v-if="loadingConvos" class="convo-empty">Loading…</div>

        <div v-else-if="convosError" class="convo-empty text-danger">{{ convosError }}</div>

        <div v-else-if="conversations.length === 0" class="convo-empty">
          No conversations yet.<br>
          Browse books and click "Message owner" to start one.
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
            <div class="convo-name">{{ convo.user.name }}</div>
            <div class="convo-last">{{ convo.last_message?.body }}</div>
          </div>
          <div v-if="convo.unread > 0" class="convo-badge">{{ convo.unread }}</div>
        </div>
      </aside>

      <!-- Ziņojumu panelis -->
      <main class="thread-panel" :class="{ 'hidden-mobile': !showThread }">

        <!-- Nav izvēlēta sarakstes -->
        <div v-if="!activeUser" class="thread-empty">
          <span class="thread-empty-icon">💬</span>
          <p>Select a conversation to start chatting.</p>
        </div>

        <template v-else>
          <!-- Sarakstes galvene -->
          <div class="thread-header">
            <button class="back-btn" @click="showThread = false">← Back</button>
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

          <!-- Ziņojumi -->
          <div class="thread-messages" ref="messageList" role="log" aria-live="polite" aria-label="Ziņojumi">
            <div v-if="loadingThread" class="thread-status">Loading…</div>
            <div v-if="threadError" class="text-center text-danger p-3">{{ threadError }}</div>
            <div v-else-if="messages.length === 0" class="thread-status">
              No messages yet. Say hello!
            </div>

            <div
              v-for="msg in messages"
              :key="msg.id"
              class="msg-row"
              :class="{ 'msg-mine': msg.from_user_id === authStore.user.id }"
            >
              <div class="msg-bubble">
                <p class="msg-body">{{ msg.body }}</p>
                <span class="msg-time">{{ formatTime(msg.created_at) }}</span>
              </div>
            </div>
          </div>

          <!-- Bloķēts paziņojums -->
          <div v-if="activeUserBlocked" class="thread-blocked-notice">{{ t('msg.blocked') }}</div>

          <!-- Rakstīt ziņu -->
          <div v-else class="thread-compose">
            <textarea
              v-model="compose"
              class="compose-input"
              placeholder="Type a message…"
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
    }
  },

  async mounted() {
    await this.fetchConversations()

    // Automātiski atver sarakstes ja pārnāk no grāmatas kartiņas vai profila
    if (this.$route.query.userId) {
      const userId   = Number(this.$route.query.userId)
      const userName = this.$route.query.userName || 'User'
      await this.openConversation({ id: userId, name: userName })
    }
  },

  beforeUnmount() {
    clearInterval(this.pollTimer)
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

      // pārbaudam vai bloķēts
      try {
        const { data } = await axios.get(`/api/users/${user.id}`)
        this.activeUserBlocked = (data.is_blocked || data.they_blocked_me) ?? false
      } catch { /* ignore */ }

      await this.fetchThread()

      // Pārbauda jaunas ziņas ik 8s kamēr sarakstes ir atvērta
      this.pollTimer = setInterval(() => {
        if (this.activeUser) this.fetchThread(true)
      }, 8000)
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
      } catch { /* ignore */ }
    },

    async fetchThread(silent = false) {
      if (!silent) this.loadingThread = true
      this.threadError = null
      try {
        const { data } = await axios.get(`/api/messages/${this.activeUser.id}`)
        this.messages = data

        // Atzīmē sarakstes kā izlasītu sarakstā
        const convo = this.conversations.find(c => c.user.id === this.activeUser.id)
        if (convo) convo.unread = 0

        await this.$nextTick()
        this.scrollToBottom()
      } catch (err) {
        if (!silent) this.threadError = err.response?.data?.message || 'Failed to load messages.'
      } finally {
        this.loadingThread = false
      }
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

        // Atjauno sarakstes sarakstu
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

    formatTime(dateStr) {
      return new Date(dateStr).toLocaleString('en-GB', {
        day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
      })
    },

  },
}
</script>
