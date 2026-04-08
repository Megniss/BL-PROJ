<template>
  <Teleport to="body">
    <div v-if="open" class="modal-overlay" @click.self="$emit('close')">
      <div class="modal-card">
        <h2 class="modal-title mb-2">{{ t('profile.ratingTitle') }}: {{ book?.title }}</h2>

        <div v-if="error" class="alert alert-danger py-2 px-3 mb-3">{{ error }}</div>

        <label class="form-label fw-semibold">{{ t('profile.ratingStars') }}</label>
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

        <label for="rating-review" class="form-label fw-semibold">{{ t('profile.ratingReview') }}</label>
        <textarea
          id="rating-review"
          v-model="review"
          class="form-control mb-3"
          rows="3"
          maxlength="500"
          :placeholder="t('profile.ratingPlaceholder')"
        ></textarea>

        <div class="d-flex justify-content-end gap-2">
          <button class="btn btn-outline-secondary" @click="$emit('close')">{{ t('up.cancel') }}</button>
          <button
            class="btn btn-primary"
            :disabled="stars === 0 || sending"
            @click="handleSubmit"
          >
            {{ sending ? t('profile.ratingSubmitting') : t('profile.ratingSubmit') }}
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
