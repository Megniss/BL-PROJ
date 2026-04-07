<template>
  <Teleport to="body">
    <div v-if="open" class="modal-overlay" @click.self="$emit('close')">
      <div class="modal-card">
        <h2 class="modal-title mb-2">Rate: {{ book?.title }}</h2>

        <div v-if="error" class="alert alert-danger py-2 px-3 mb-3">{{ error }}</div>

        <label class="form-label fw-semibold">Your rating</label>
        <div class="star-picker mb-3">
          <span
            v-for="n in 5"
            :key="n"
            class="star"
            :class="{ active: n <= stars }"
            @click="stars = n"
            style="cursor:pointer; font-size:1.5rem"
          >★</span>
        </div>

        <label class="form-label fw-semibold">Review (optional)</label>
        <textarea
          v-model="review"
          class="form-control mb-3"
          rows="3"
          maxlength="500"
          placeholder="Write a short review..."
        ></textarea>

        <div class="d-flex justify-content-end gap-2">
          <button class="btn btn-outline-secondary" @click="$emit('close')">Cancel</button>
          <button
            class="btn btn-primary"
            :disabled="stars === 0 || sending"
            @click="handleSubmit"
          >
            {{ sending ? 'Submitting…' : 'Submit Rating' }}
          </button>
        </div>

      </div>
    </div>
  </Teleport>
</template>

<script>
import langMixin from '../langMixin.js'

export default {
  name: 'RatingModal',
  mixins: [langMixin],

  props: {
    open: { type: Boolean, required: true },
    book: { type: Object, default: null },
    sending: { type: Boolean, default: false },
    error: { type: String, default: '' },
  },

  emits: ['close', 'submit'],

  data() {
    return {
      stars: 0,
      review: '',
    }
  },

  watch: {
    open(val) {
      if (val) {
        this.stars = 0
        this.review = ''
      }
    }
  },

  methods: {
    handleSubmit() {
      if (this.stars === 0 || this.sending) return
      this.$emit('submit', { stars: this.stars, review: this.review })
    }
  }
}
</script>

<style>
.star-picker .star { color: #ccc; transition: color 0.15s; }
.star-picker .star.active { color: #f5a623; }
</style>
