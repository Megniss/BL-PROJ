// shared logic for Home.vue

export default {

  computed: {
    steps() {
      return [
        { number: '01', icon: '📖', title: this.t('how.step1.title'), desc: this.t('how.step1.desc') },
        { number: '02', icon: '🔍', title: this.t('how.step2.title'), desc: this.t('how.step2.desc') },
        { number: '03', icon: '🤝', title: this.t('how.step3.title'), desc: this.t('how.step3.desc') },
      ]
    },
  },

  methods: {
    goToLogin() {
      this.$router.push({ name: 'login' })
    },

    goToRegister() {
      this.$router.push({ name: 'register' })
    },

    scrollToBooks() {
      const el = document.getElementById('books')
      if (el) el.scrollIntoView({ behavior: 'smooth' })
    },

    scrollToHowItWorks() {
      const el = document.getElementById('how-it-works')
      if (el) el.scrollIntoView({ behavior: 'smooth' })
    },
  }
}
