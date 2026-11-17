<template>
  <div class="messages-page">

    <AppNavbar />

    <div class="messages-body">

      <aside class="convo-panel" :class="{ 'hidden-mobile': showThread }">
        <div class="convo-header">
          <h2 class="convo-title">{{ t('messages.title') }}</h2>
        </div>

        <!-- atbalsts vienmēr pirmais, adminam nav (viņš atbild admin panelī) -->
        <div
          v-if="!authStore.user?.is_admin"
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
        <div v-if="!authStore.user?.is_admin" class="convo-divider"></div>

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
            <div class="convo-last">{{ convo.last_message?.body || (convo.last_message?.image ? '📷 ' + t('messages.photo') : '') }}</div>
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
              @click="!activeUser.deleted && $router.push({ name: 'userProfile', params: { id: activeUser.id } })"
              @keyup.enter="!activeUser.deleted && $router.push({ name: 'userProfile', params: { id: activeUser.id } })"
            >{{ activeUser.name }}</span>
            <button v-if="!activeUser.deleted" class="thread-block-btn" :class="{ blocked: activeUserBlocked }" @click="toggleBlock">
              {{ activeUserBlocked ? t('up.unblock') : t('up.block') }}
            </button>
          </div>

          <ChatThread
            ref="thread"
            :messages="threadMessages"
            :loading="loadingThread"
            :error="threadError"
            :notice="activeUser.deleted ? t('msg.deletedUser') : (activeUserBlocked ? t('msg.blocked') : '')"
            :typing="otherTyping"
            editable
            :send="sendMessage"
            :edit="editMessage"
            :unsend="unsendMessage"
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
import langMixin from '../langMixin.js'
import AppNavbar from './AppNavbar.vue'
import ChatThread from './ChatThread.vue'
import { messageForm, sendTyping, fetchTyping, addNew, POLL_MS } from '../chat.js'

export default {
  name: 'Messages',

  components: { AppNavbar, ChatThread },

  mixins: [langMixin],

  data() {
    return {
      authStore,
      conversations: [],
      activeUser: null,
      messages: [],
      loadingConvos: true,
      loadingThread: false,
      showThread: false,
      pollTimer: null,
      convosError: null,
      threadError: null,
      activeUserBlocked: false,
      otherTyping: false,
      retried: new Set(),
    }
  },

  computed: {
    // ChatThread vajag vienādu formu abiem čatiem
    threadMessages() {
      const me = this.authStore.user.id
      return this.messages.map(m => ({ ...m, mine: m.from_user_id === me, read: !!m.read_at }))
    },
    chatKey() {
      return this.activeUser ? `user:${this.activeUser.id}` : null
    },
  },

  async mounted() {
    await this.fetchConversations()

    // ja atnācām no grāmatas kartītes vai profila, uzreiz atver sarunu
    if (this.$route.query.userId) {
      const userId = Number(this.$route.query.userId)
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
        this.convosError = err.response?.data?.message || this.t('messages.loadConvosError')
      } finally {
        this.loadingConvos = false
      }
    },

    async openConversation(user) {
      this.activeUser = user
      this.activeUserBlocked = false
      this.otherTyping = false
      this.showThread = true
      clearInterval(this.pollTimer)
      this.$refs.thread?.reset()

      // pārbauda bloķēšanu pirms rāda sarunu
      try {
        const { data } = await axios.get(`/api/users/${user.id}`)
        this.activeUserBlocked = (data.is_blocked || data.they_blocked_me) ?? false
      } catch { /* ignorē */ }

      await this.fetchThread()

      // reizi 3 sekundēs jaunas ziņas un vai otrs raksta
      this.pollTimer = setInterval(() => {
        if (this.activeUser) this.poll()
      }, POLL_MS)
    },

    async toggleBlock() {
      try {
        if (this.activeUserBlocked) {
          await axios.delete(`/api/blocks/${this.activeUser.id}`)
          this.activeUserBlocked = false
        } else {
          await axios.post(`/api/blocks/${this.activeUser.id}`)
          this.activeUserBlocked = true
          this.otherTyping = false
        }
        // atjaunina bloķēšanas tagu sānbārā arī
        const convo = this.conversations.find(c => c.user.id === this.activeUser.id)
        if (convo) convo.is_blocked = this.activeUserBlocked
      } catch { /* ignorē */ }
    },

    async fetchThread() {
      this.loadingThread = true
      this.threadError = null
      this.messages = []
      try {
        const { data } = await axios.get(`/api/messages/${this.activeUser.id}`)
        this.messages = data

        const convo = this.conversations.find(c => c.user.id === this.activeUser.id)
        if (convo) convo.unread = 0
      } catch (err) {
        this.threadError = err.response?.data?.message || this.t('messages.loadError')
      } finally {
        this.loadingThread = false
      }
    },

    async poll() {
      const user = this.activeUser
      const lastId = this.messages.at(-1)?.id ?? 0
      try {
        const [{ data }, typing] = await Promise.all([
          axios.get(`/api/messages/${user.id}`),
          this.activeUserBlocked ? false : fetchTyping(`user:${user.id}`),
        ])
        // kamēr gaidīja, varēja atvērt citu sarunu
        if (this.activeUser?.id !== user.id) return

        this.otherTyping = typing

        // izlasīts statuss manām ziņām mainās arī vecajām
        for (const m of data) {
          const mine = this.messages.find(x => x.id === m.id)
          if (mine && !mine.read_at && m.read_at) mine.read_at = m.read_at
        }

        const fresh = data.filter(m => m.id > lastId)
        if (fresh.length === 0) return
        addNew(this.messages, fresh)
        // ziņa atnāca - vairs neraksta
        this.otherTyping = false

        const convo = this.conversations.find(c => c.user.id === user.id)
        if (convo) {
          convo.last_message = { body: fresh.at(-1).body, image: !!fresh.at(-1).image_url }
          convo.unread = 0
        }
      } catch { /* klusums */ }
    },

    onTyping() {
      if (this.chatKey && !this.activeUserBlocked) sendTyping(this.chatKey)
    },

    // ChatThread izsauc, kļūda tiek parādīta tur
    async sendMessage(body, image) {
      const { data } = await axios.post('/api/messages', messageForm(body, image, { to_user_id: this.activeUser.id }))
      addNew(this.messages, [data])

      // atjaunina vai izveido ierakstu sānbārā
      const last = { body, image: !!image, created_at: data.created_at }
      const convo = this.conversations.find(c => c.user.id === this.activeUser.id)
      if (convo) {
        convo.last_message = last
      } else {
        this.conversations.unshift({ user: this.activeUser, last_message: last, unread: 0 })
      }
    },

    async editMessage(msg, body) {
      const { data } = await axios.put(`/api/messages/${msg.id}`, { body })
      const idx = this.messages.findIndex(m => m.id === msg.id)
      if (idx !== -1) this.messages[idx] = data
    },

    async unsendMessage(msg) {
      await axios.delete(`/api/messages/${msg.id}`)
      this.messages = this.messages.filter(m => m.id !== msg.id)
    },

    // saite novecojusi - paņem jaunu, katrai ziņai tikai vienreiz
    async refreshImage(msg) {
      if (this.retried.has(msg.id)) return
      this.retried.add(msg.id)
      try {
        const { data } = await axios.get(`/api/messages/${this.activeUser.id}`)
        const fresh = data.find(m => m.id === msg.id)
        const orig = this.messages.find(m => m.id === msg.id)
        if (fresh?.image_url && orig) orig.image_url = fresh.image_url
      } catch { /* ignorē */ }
    },
  },
}
</script>
