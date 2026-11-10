<template>
  <div class="books-map-wrap">
    <div class="books-map-stats">
      <span v-if="points.length" class="map-chip">📚 {{ t('map.booksAvailable').replace('{n}', totalBooks) }}</span>
      <span v-else-if="loaded" class="map-chip">{{ t('map.empty') }}</span>
    </div>

    <div ref="map" class="books-map" :style="{ height }"></div>

    <p class="books-map-note">🔒 {{ t('map.note') }}</p>
  </div>
</template>

<script>
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import langMixin from '../langMixin.js'
import { tileLayer } from '../mapTiles.js'
import { coverColor } from '../coverColor.js'

// cik pikseļu tuvumā punktus apvieno vienā
const CLUSTER_PX = 60

function escape(text) {
  return String(text).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
}

const bookKey = b => (b.title + '|' + b.author).toLowerCase()

// vairāku punktu grāmatas kopā — vienādās vienreiz ar kopējo skaitu
function mergeBooks(points) {
  const all = {}
  for (const p of points) {
    for (const b of p.books) {
      const key = bookKey(b)
      if (all[key]) {
        all[key].copies += b.copies
        all[key].cover_image ??= b.cover_image
      } else {
        all[key] = { ...b }
      }
    }
  }
  return Object.values(all).sort((a, b) => a.title.localeCompare(b.title, 'lv'))
}

export default {
  name: 'BooksMap',
  mixins: [langMixin],
  props: {
    height: { type: String, default: '520px' },
  },
  emits: ['book'],

  data() {
    return { points: [], loaded: false }
  },

  computed: {
    totalBooks() {
      return mergeBooks(this.points).length
    },
  },

  async mounted() {
    this.map = L.map(this.$refs.map).setView([56.88, 24.6], 7)
    tileLayer().addTo(this.map)
    this.layer = L.layerGroup().addTo(this.map)
    this.map.on('zoomend', () => this.draw())

    // uzspiežot grāmatu logā, vecāks atver grāmatas logu
    this.map.on('popupopen', e => {
      e.popup.getElement().querySelectorAll('[data-book]').forEach(el => {
        el.addEventListener('click', () => {
          const book = this.bookById[el.dataset.book]
          this.map.closePopup()
          if (book) this.$emit('book', book)
        })
      })
    })

    try {
      const { data } = await axios.get('/api/books/map')
      this.points = data
    } catch { /* tukša karte */ }
    this.loaded = true

    if (this.points.length) {
      this.map.fitBounds(L.latLngBounds(this.points.map(p => [p.lat, p.lng])), { padding: [40, 40], maxZoom: 9 })
    }
    this.draw()
  },

  beforeUnmount() {
    this.map?.remove()
  },

  methods: {
    popupHtml(books) {
      const rows = books.map(b => {
        const cover = b.cover_image
          ? `background-image:url('/storage/${encodeURI(b.cover_image)}')`
          : `background:${coverColor(b)}`
        const copies = b.copies > 1 ? `<span class="mp-copies">×${b.copies}</span>` : ''
        return `<li data-book="${b.id}"><span class="mp-cover" style="${cover}"></span>` +
          `<span class="mp-text"><strong>${escape(b.title)}</strong><small>${escape(b.author)}</small></span>${copies}</li>`
      }).join('')

      return '<div class="mp-books">' +
        `<p class="mp-title">${escape(this.t('map.booksHere').replace('{n}', books.length))}</p>` +
        `<ul class="map-popup-books">${rows}</ul>` +
        `<p class="mp-hint">${escape(this.t('map.clickBook'))}</p>` +
        '</div>'
    },

    // atveras uzbraucot ar peli un paliek, kamēr pele ir uz punkta vai loga
    hoverPopup(marker, html) {
      marker.bindPopup(html, { maxWidth: 280, minWidth: 240, closeButton: false, className: 'map-books-popup', offset: [0, -14] })

      let timer
      const keep = () => clearTimeout(timer)
      const close = () => { timer = setTimeout(() => marker.closePopup(), 250) }

      marker.on('mouseover', () => { keep(); marker.openPopup() })
      marker.on('mouseout', close)
      marker.on('popupopen', e => {
        const el = e.popup.getElement()
        el.onmouseenter = keep
        el.onmouseleave = close
      })
    },

    draw() {
      this.layer.clearLayers()
      this.bookById = {}
      const zoom = this.map.getZoom()

      // sagrupē punktus, kas ekrānā ir tuvu viens otram
      const groups = []
      for (const p of this.points) {
        const px = this.map.project([p.lat, p.lng], zoom)
        const near = groups.find(g => g.px.distanceTo(px) < CLUSTER_PX)
        if (near) near.points.push(p)
        else groups.push({ px, points: [p] })
      }

      for (const group of groups) {
        const books = mergeBooks(group.points)
        books.forEach(b => { this.bookById[b.id] = b })

        const bounds = L.latLngBounds(group.points.map(p => [p.lat, p.lng]))
        const size = books.length < 10 ? 34 : 42
        const marker = L.marker(bounds.getCenter(), {
          icon: L.divIcon({
            className: '',
            html: `<div class="map-cluster${group.points.length > 1 ? ' map-cluster-many' : ''}" style="width:${size}px;height:${size}px">${books.length}</div>`,
            iconSize: [size, size],
          }),
        }).addTo(this.layer)

        this.hoverPopup(marker, this.popupHtml(books))

        // apvienotu punktu uzspiežot pietuvina
        if (group.points.length > 1) {
          marker.on('click', () => this.map.fitBounds(bounds, { padding: [60, 60] }))
        }
      }
    },
  },
}
</script>

<style scoped>
.books-map {
  border-radius: 14px;
  border: 1px solid var(--border);
  box-shadow: 0 4px 18px rgba(26, 22, 18, 0.08);
  overflow: hidden;
  z-index: 0;
}
.books-map-stats { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-bottom: 12px; min-height: 30px; }
.map-chip {
  background: var(--green-light);
  color: var(--green);
  border: 1px solid var(--green-border);
  border-radius: 999px;
  padding: 4px 12px;
  font-size: 13px;
  font-weight: 600;
}
.books-map-note { text-align: center; color: var(--muted); font-size: 12px; margin: 10px 0 0; }
</style>
