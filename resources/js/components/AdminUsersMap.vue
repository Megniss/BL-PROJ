<template>
  <div>
    <p class="text-muted small mb-3">
      {{ t('adminMap.summary').replace('{n}', located.length).replace('{total}', users.length) }}
    </p>

    <div class="d-flex flex-wrap gap-3 small mb-2">
      <span><span class="map-dot" style="background:#3a6b3e"></span> {{ t('admin.role.user') }}</span>
      <span><span class="map-dot" style="background:#7b4fa0"></span> {{ t('admin.role.admin') }}</span>
      <span><span class="map-dot" style="background:#c0392b"></span> {{ t('admin.status.blocked') }}</span>
    </div>

    <div ref="map" class="admin-users-map"></div>
  </div>
</template>

<script>
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import langMixin from '../langMixin.js'
import { tileLayer } from '../mapTiles.js'

// lietotāja vārds nonāk HTML, tāpēc jāaizsargā
function escape(text) {
  return String(text).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
}

function color(user) {
  if (user.is_blocked) return '#c0392b'
  if (user.is_admin) return '#7b4fa0'
  return '#3a6b3e'
}

export default {
  name: 'AdminUsersMap',
  mixins: [langMixin],
  props: {
    users: { type: Array, required: true },
  },

  computed: {
    located() {
      return this.users.filter(u => u.latitude !== null && u.longitude !== null)
    },
  },

  watch: {
    users() {
      this.drawUsers()
    },
  },

  mounted() {
    this.map = L.map(this.$refs.map).setView([56.88, 24.6], 7)
    tileLayer().addTo(this.map)
    this.layer = L.featureGroup().addTo(this.map)
    this.drawUsers()
  },

  beforeUnmount() {
    this.map?.remove()
  },

  methods: {
    drawUsers() {
      if (!this.map) return
      this.layer.clearLayers()

      // vienā ~1 km punktā var būt vairāki lietotāji
      const groups = {}
      for (const u of this.located) {
        const key = u.latitude + ',' + u.longitude
        ;(groups[key] ??= []).push(u)
      }

      for (const group of Object.values(groups)) {
        const first = group[0]
        const popup = group.map(u =>
          `<strong>${escape(u.name)}</strong><br>${escape(u.email)}<br>` +
          `${u.books_count} ${escape(this.t('admin.col.books').toLowerCase())}`
        ).join('<hr class="my-1">')

        L.circleMarker([first.latitude, first.longitude], {
          radius: group.length > 1 ? 10 : 7,
          color: '#fff',
          weight: 2,
          fillColor: color(first),
          fillOpacity: 1,
        }).bindPopup(popup).addTo(this.layer)
      }

      if (this.located.length) {
        this.map.fitBounds(this.layer.getBounds(), { padding: [30, 30], maxZoom: 11 })
      }
    },
  },
}
</script>

<style scoped>
.admin-users-map {
  height: 520px;
  border-radius: 10px;
  border: 1px solid var(--border);
  z-index: 0;
}
.map-dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  margin-right: 4px;
  vertical-align: middle;
}
</style>
