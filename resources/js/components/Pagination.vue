<template>
  <div v-if="total > 1" class="pagination-bar">
    <button class="pg-btn" :disabled="current === 1" @click="$emit('change', 1)" title="First">«</button>
    <button class="pg-btn" :disabled="current === 1" @click="$emit('change', current - 1)" title="Previous">‹</button>

    <template v-for="item in pages" :key="item">
      <span v-if="item === '...'" class="pg-ellipsis">…</span>
      <button v-else class="pg-btn" :class="{ active: item === current }" @click="$emit('change', item)">{{ item }}</button>
    </template>

    <button class="pg-btn" :disabled="current === total" @click="$emit('change', current + 1)" title="Next">›</button>
    <button class="pg-btn" :disabled="current === total" @click="$emit('change', total)" title="Last">»</button>
  </div>
</template>

<script>
export default {
  name: 'Pagination',
  props: {
    current: { type: Number, required: true },
    total:   { type: Number, required: true },
  },
  emits: ['change'],
  computed: {
    pages() {
      const c = this.current
      const t = this.total
      if (t <= 7) return Array.from({ length: t }, (_, i) => i + 1)

      const items = []
      // always first
      items.push(1)

      if (c > 3) items.push('...')

      // window around current
      const from = Math.max(2, c - 1)
      const to   = Math.min(t - 1, c + 1)
      for (let i = from; i <= to; i++) items.push(i)

      if (c < t - 2) items.push('...')

      // always last
      items.push(t)
      return items
    }
  }
}
</script>
