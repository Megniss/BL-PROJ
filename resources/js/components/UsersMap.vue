<template>
  <div>
    <p v-if="loaded && users.length === 0" class="text-muted small mb-2">{{ t('map.empty') }}</p>
    <div ref="map" class="users-map" :style="{ height }"></div>
    <p class="text-muted small mt-2 mb-0">{{ t('map.note') }}</p>
  </div>
</template>

<script>
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import langMixin from '../langMixin.js'
import { tileLayer } from '../mapTiles.js'

// cik pikseļu tuvumā punktus apvieno vienā
const CLUSTER_PX = 60

function escape(text) {
  return String(text).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
}

export default {
  name: 'UsersMap',
  mixins: [langMixin],
  props: {
    height: { type: String, default: '520px' },
  },

  data() {
    return { users: [], loaded: false }
  },

  async mounted() {
    this.map = L.map(this.$refs.map, { scrollWheelZoom: false }).setView([56.88, 24.6], 7)
    tileLayer().addTo(this.map)
    this.layer = L.layerGroup().addTo(this.map)

    this.map.on('zoomend', () => this.draw())
    // saite uz profilu bez lapas pārlādes
    this.map.on('popupopen', e => {
      e.popup.getElement().querySelectorAll('a[data-user]').forEach(a => {
        a.addEventListener('click', ev => {
          ev.preventDefault()
          this.$router.push({ name: 'userProfile', params: { id: a.dataset.user } })
        })
      })
    })

    try {
      const { data } = await axios.get('/api/users/map')
      this.users = data
    } catch { /* tukša karte */ }
    this.loaded = true

    if (this.users.length) {
      this.map.fitBounds(L.latLngBounds(this.users.map(u => [u.lat, u.lng])), { padding: [40, 40], maxZoom: 9 })
    }
    this.draw()
  },

  beforeUnmount() {
    this.map?.remove()
  },

  methods: {
    popupHtml(list) {
      return list.map(u =>
        `<strong>${escape(u.name)}</strong><br>` +
        `${u.books} ${escape(this.t('map.books'))}<br>` +
        `<a href="/users/${u.id}" data-user="${u.id}">${escape(this.t('map.profile'))} →</a>`
      ).join('<hr class="my-1">')
    },

    draw() {
      this.layer.clearLayers()
      const zoom = this.map.getZoom()

      // sagrupē punktus, kas ekrānā ir tuvu viens otram
      const groups = []
      for (const u of this.users) {
        const p = this.map.project([u.lat, u.lng], zoom)
        const near = groups.find(g => g.point.distanceTo(p) < CLUSTER_PX)
        if (near) near.list.push(u)
        else groups.push({ point: p, list: [u] })
      }

      for (const { list } of groups) {
        if (list.length === 1) {
          const u = list[0]
          L.circleMarker([u.lat, u.lng], { radius: 8, color: '#fff', weight: 2, fillColor: '#3a6b3e', fillOpacity: 1 })
            .bindPopup(this.popupHtml(list))
            .addTo(this.layer)
          continue
        }

        const bounds = L.latLngBounds(list.map(u => [u.lat, u.lng]))
        const size = list.length < 10 ? 34 : 42
        const cluster = L.marker(bounds.getCenter(), {
          icon: L.divIcon({
            className: '',
            html: `<div class="map-cluster" style="width:${size}px;height:${size}px">${list.length}</div>`,
            iconSize: [size, size],
          }),
        }).addTo(this.layer)

        // visi vienā vietā vai jau pietuvināts — parāda sarakstu, citādi pietuvina
        const samePlace = bounds.getNorthEast().equals(bounds.getSouthWest())
        if (samePlace || zoom >= this.map.getMaxZoom()) {
          cluster.bindPopup(this.popupHtml(list), { maxHeight: 240 })
        } else {
          cluster.on('click', () => this.map.fitBounds(bounds, { padding: [60, 60] }))
        }
      }
    },
  },
}
</script>

<style scoped>
.users-map {
  border-radius: 12px;
  border: 1px solid var(--border);
  z-index: 0;
}
</style>
