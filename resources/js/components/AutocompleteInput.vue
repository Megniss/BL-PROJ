<template>
  <div class="autocomplete">
    <input
      :id="id"
      :value="modelValue"
      class="form-control"
      type="text"
      autocomplete="off"
      :required="required"
      @input="onInput"
      @focus="focused = true"
      @blur="focused = false"
      @keydown.down.prevent="move(1)"
      @keydown.up.prevent="move(-1)"
      @keydown.enter="onEnter"
      @keydown.esc="items = []"
    />
    <ul v-if="focused && items.length" class="autocomplete-list">
      <li
        v-for="(item, i) in items"
        :key="i"
        class="autocomplete-item"
        :class="{ active: i === active, disabled: isDisabled(item) }"
        @mousedown.prevent="choose(item)"
      >
        <slot :item="item">{{ item }}</slot>
      </li>
    </ul>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'AutocompleteInput',
  props: {
    modelValue: { type: String, default: '' },
    url: { type: String, required: true },
    id: { type: String, default: null },
    required: Boolean,
    // ieteikumi, ko nevar izvēlēties (piem. grāmata, kas jau ir)
    disabledWhen: { type: Function, default: () => false },
  },
  emits: ['update:modelValue', 'select'],

  data() {
    return { items: [], active: -1, focused: false }
  },

  beforeUnmount() {
    clearTimeout(this.timer)
  },

  methods: {
    onInput(e) {
      this.$emit('update:modelValue', e.target.value)
      clearTimeout(this.timer)
      this.timer = setTimeout(() => this.fetch(e.target.value), 250)
    },

    async fetch(q) {
      if (q.trim().length < 2) {
        this.items = []
        return
      }
      try {
        const { data } = await axios.get(this.url, { params: { q } })
        this.items = data
        this.active = -1
      } catch {
        this.items = []
      }
    },

    isDisabled(item) {
      return this.disabledWhen(item)
    },

    move(step) {
      if (!this.items.length) return
      this.active = (this.active + step + this.items.length) % this.items.length
    },

    onEnter(e) {
      if (this.active < 0 || !this.items.length) return
      e.preventDefault()
      this.choose(this.items[this.active])
    },

    choose(item) {
      if (this.isDisabled(item)) return
      this.$emit('select', item)
      this.items = []
    },
  },
}
</script>
