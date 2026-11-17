<template>
  <!-- kopīgā čata daļa: ziņas, bildes, labošana, rakstīšana -->
  <!-- izmanto gan Messages.vue (lietotāji), gan Support.vue (atbalsts) -->
  <div class="thread-messages" ref="messageList" role="log" aria-live="polite" aria-label="Ziņojumi">
    <div v-if="loading" class="thread-status">{{ t('messages.loading') }}</div>
    <div v-if="error" class="text-center text-danger p-3">{{ error }}</div>
    <div v-else-if="!loading && messages.length === 0" class="thread-status">{{ emptyText || t('messages.noMessages') }}</div>

    <div
      v-for="msg in messages"
      :key="msg.id"
      class="msg-row"
      :class="{ 'msg-mine': msg.mine }"
    >
      <div
        class="msg-bubble"
        :class="{ 'msg-bubble-read': msg.mine && msg.read, 'msg-bubble-sent': msg.mine && !msg.read }"
        @contextmenu.prevent="canEdit(msg) ? openCtxMenu(msg, $event) : null"
        @touchstart="canEdit(msg) ? startLongPress(msg, $event) : null"
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
          <img
            v-if="msg.image_url"
            :src="msg.image_url"
            class="msg-image"
            :alt="t('messages.photo')"
            loading="lazy"
            @click="lightbox = msg.image_url"
            @load="onImageLoad"
            @error="onImageError(msg)"
          />
          <p v-else-if="msg.image_removed" class="msg-image-removed">{{ t('messages.photoRemoved') }}</p>
          <p v-if="msg.body" class="msg-body">{{ msg.body }}</p>
          <div class="msg-meta">
            <span v-if="msg.tag" class="support-admin-tag" :data-name="msg.tagTitle">{{ msg.tag }}</span>
            <span v-if="msg.tagName" class="support-admin-name">{{ msg.tagName }}</span>
            <span v-if="msg.edited_at" class="msg-edited">{{ t('messages.edited') }}</span>
            <span class="msg-time">{{ formatTime(msg.created_at) }}</span>
          </div>
        </template>
      </div>
    </div>

    <!-- otrs raksta -->
    <div v-if="typing" class="msg-row typing-row" :aria-label="t('messages.typing')">
      <div class="msg-bubble typing-bubble">
        <span class="typing-dot"></span>
        <span class="typing-dot"></span>
        <span class="typing-dot"></span>
      </div>
    </div>

    <!-- labā poga / garš pieskāriens -->
    <div v-if="ctxMenu.visible" class="msg-ctx-menu" :style="{ top: ctxMenu.y + 'px', left: ctxMenu.x + 'px' }">
      <button @click="startEdit(ctxMenu.msg); ctxMenu.visible = false">{{ t('messages.editBtn') }}</button>
      <button @click="doUnsend(ctxMenu.msg); ctxMenu.visible = false" class="msg-ctx-danger">{{ t('messages.unsendBtn') }}</button>
    </div>
  </div>

  <div v-if="notice" class="thread-blocked-notice">{{ notice }}</div>

  <template v-else>
    <!-- bilde pirms sūtīšanas -->
    <div v-if="pendingPreview || composeError" class="compose-image-preview">
      <div v-if="pendingPreview" class="compose-image-thumb">
        <img :src="pendingPreview" :alt="t('messages.photo')" />
        <button class="compose-image-remove" @click="clearImage" :aria-label="t('messages.removePhoto')">✕</button>
      </div>
      <span v-if="composeError" class="text-danger small">{{ composeError }}</span>
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
        aria-label="Rakstīt ziņu"
        rows="1"
        @keydown.enter.exact.prevent="doSend"
        @input="onInput"
        @paste="onPaste"
        ref="composeInput"
      ></textarea>
      <button
        class="compose-send-btn"
        :disabled="!canSend"
        :class="{ active: canSend }"
        @click="doSend"
        aria-label="Send"
      >{{ sending || preparingImage ? '…' : '➤' }}</button>
    </div>
  </template>

  <Teleport to="body">
    <div v-if="lightbox" class="msg-lightbox" @click="lightbox = null">
      <img :src="lightbox" :alt="t('messages.photo')" />
    </div>
  </Teleport>
</template>

<script>
import langMixin from '../langMixin.js'
import { resizeImage } from '../imageResize.js'

// cik bieži sūta "raksta" signālu (serverī tas dzīvo 5 s)
const TYPING_EVERY = 2500

export default {
  name: 'ChatThread',

  mixins: [langMixin],

  props: {
    // ziņas jau vienādā formā: id, body, image_url, image_removed, created_at, edited_at, mine, read, tag
    messages: { type: Array, default: () => [] },
    loading: Boolean,
    error: String,
    emptyText: String,
    // ja ir teksts, rakstīšanas vietā rāda šo (bloķēts, slēgts u.t.t.)
    notice: String,
    typing: Boolean,
    // tikai parastajā čatā var labot un atsaukt
    editable: Boolean,
    // API izsaukumi nāk no vecāka komponenta
    send: { type: Function, required: true },
    edit: Function,
    unsend: Function,
    refreshImage: Function,
  },

  emits: ['typing'],

  data() {
    return {
      compose: '',
      sending: false,
      pendingImage: null,
      pendingPreview: null,
      preparingImage: false,
      composeError: '',
      lightbox: null,
      editingId: null,
      editBody: '',
      editSaving: false,
      ctxMenu: { visible: false, x: 0, y: 0, msg: null },
      longPressTimer: null,
      lastTypingAt: 0,
    }
  },

  computed: {
    canSend() {
      return (this.compose.trim() || this.pendingImage) && !this.sending && !this.preparingImage
    },
  },

  watch: {
    // jaunas ziņas - uz leju, ja jau bija lejā vai ziņa ir mana
    'messages.length'() {
      const near = this.isNearBottom()
      this.$nextTick(() => {
        if (near || this.messages.at(-1)?.mine) this.scrollToBottom()
      })
    },
    typing(val) {
      if (val && this.isNearBottom()) this.$nextTick(() => this.scrollToBottom())
    },
  },

  mounted() {
    document.addEventListener('click', this.closeCtxMenu)
    document.addEventListener('keydown', this.onKeydown)
    this.$nextTick(() => this.scrollToBottom())
  },

  beforeUnmount() {
    document.removeEventListener('click', this.closeCtxMenu)
    document.removeEventListener('keydown', this.onKeydown)
    this.clearImage()
  },

  methods: {
    // vecāks var izsaukt pēc sarunas ielādes
    reset() {
      this.compose = ''
      this.composeError = ''
      this.clearImage()
      this.cancelEdit()
      this.lastTypingAt = 0
      this.$nextTick(() => this.scrollToBottom())
    },

    canEdit(msg) {
      return this.editable && msg.mine && !msg.read && this.editingId !== msg.id
    },

    async doSend() {
      if (!this.canSend) return
      const body = this.compose.trim()
      const image = this.pendingImage

      this.sending = true
      this.composeError = ''
      try {
        await this.send(body, image)
        this.compose = ''
        this.clearImage()
        this.lastTypingAt = 0
        this.$nextTick(() => {
          if (this.$refs.composeInput) this.$refs.composeInput.style.height = 'auto'
          this.scrollToBottom()
        })
      } catch (err) {
        // teksts paliek laukā, lai nepazūd
        this.composeError = err.response?.data?.message || this.t('messages.sendError')
      } finally {
        this.sending = false
      }
    },

    onInput(e) {
      this.autoResize(e)
      // "raksta" signāls ne biežāk kā reizi 2,5 s
      const now = Date.now()
      if (this.compose.trim() && now - this.lastTypingAt > TYPING_EVERY) {
        this.lastTypingAt = now
        this.$emit('typing')
      }
    },

    async attachImage(file) {
      this.composeError = ''
      if (!file.type.startsWith('image/')) return

      this.preparingImage = true
      try {
        const resized = await resizeImage(file)
        if (resized.size > 5 * 1024 * 1024) throw new Error('too big')
        this.clearImage()
        this.pendingImage = resized
        this.pendingPreview = URL.createObjectURL(resized)
      } catch {
        this.composeError = this.t('messages.photoError')
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

    // saite novecojusi - vecāks paņem jaunu, mēģina tikai vienreiz
    onImageError(msg) {
      if (msg.imageRetried || !this.refreshImage) return
      msg.imageRetried = true
      this.refreshImage(msg)
    },

    // bilde ielādējas vēlāk par tekstu, tāpēc vēlreiz noritina uz leju
    onImageLoad() {
      if (this.isNearBottom(400)) this.scrollToBottom()
    },

    onKeydown(e) {
      if (e.key === 'Escape') this.lightbox = null
    },

    isNearBottom(gap = 150) {
      const el = this.$refs.messageList
      return !el || el.scrollHeight - el.scrollTop - el.clientHeight < gap
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

      // savas ziņas ir labajā pusē, mēģina izvietot kreisajā
      let x, y

      if (r.left - menuW - gap >= 0) {
        x = r.left - menuW - gap
        y = r.top
      } else if (r.right + menuW + gap <= window.innerWidth) {
        // pa kreisi nav vietas, liek pa labi
        x = r.right + gap
        y = r.top
      } else {
        // pēdējā iespēja: zem burbuļa
        x = Math.max(8, r.right - menuW)
        y = r.bottom + gap
      }

      // nedrīkst iziet ārpus ekrāna apakšas
      if (y + menuH > window.innerHeight) y = r.top - menuH - gap

      this.ctxMenu = { visible: true, x, y, msg }
    },

    closeCtxMenu() {
      this.ctxMenu.visible = false
    },

    startLongPress(msg, e) {
      const bubble = e.currentTarget
      this.longPressTimer = setTimeout(() => this.positionCtxMenu(msg, bubble), 500)
    },

    cancelLongPress() {
      clearTimeout(this.longPressTimer)
    },

    startEdit(msg) {
      this.editingId = msg.id
      this.editBody = msg.body
    },

    cancelEdit() {
      this.editingId = null
      this.editBody = ''
    },

    async saveEdit(msg) {
      const body = this.editBody.trim()
      if ((!body && !msg.image_url) || this.editSaving || !this.edit) return
      this.editSaving = true
      try {
        await this.edit(msg, body)
        this.cancelEdit()
      } catch (err) {
        alert(err.response?.data?.message || this.t('messages.editError'))
      } finally {
        this.editSaving = false
      }
    },

    async doUnsend(msg) {
      if (!this.unsend || !confirm(this.t('messages.unsendConfirm'))) return
      try {
        await this.unsend(msg)
      } catch (err) {
        alert(err.response?.data?.message || this.t('messages.unsendError'))
      }
    },

    formatTime(dateStr) {
      if (!dateStr) return ''
      return new Date(dateStr).toLocaleString('en-GB', {
        day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
      })
    },
  },
}
</script>
