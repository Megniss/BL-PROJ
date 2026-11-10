<template>
  <div>
    <p v-if="!readonly" class="text-muted small mb-3">{{ t('location.hint') }}</p>

    <div ref="map" class="location-map mb-3"></div>

    <div v-if="error" class="alert alert-danger py-2 px-3">{{ error }}</div>

    <div v-if="!readonly" class="d-flex flex-wrap gap-2">
      <button class="btn btn-outline-secondary" :disabled="locating" @click="useCurrent">
        {{ locating ? t('location.locating') : '📍 ' + t('location.useCurrent') }}
      </button>
      <button class="btn btn-primary" :disabled="!picked || saving || !changed" @click="save">
        {{ saving ? t('profile.saving') : t('profile.save') }}
      </button>
      <button v-if="saved" class="btn btn-outline-danger ms-auto" :disabled="saving" @click="remove">
        {{ t('location.remove') }}
      </button>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { showToast } from '../toast.js'
import langMixin from '../langMixin.js'
import { tileLayer } from '../mapTiles.js'

// Latvijas centrs, ja vieta vēl nav izvēlēta
const DEFAULT_VIEW = { center: [56.88, 24.6], zoom: 7 }

export default {
  name: 'LocationPicker',
  mixins: [langMixin],
  props: {
    latitude: { type: Number, default: null },
    longitude: { type: Number, default: null },
    // adminam tikai apskatei
    readonly: Boolean,
  },

  data() {
    return {
      picked: null,
      saved: null,
      saving: false,
      locating: false,
      error: '',
    }
  },

  computed: {
    changed() {
      return !this.saved || this.picked?.lat !== this.saved.lat || this.picked?.lng !== this.saved.lng
    },
  },

  mounted() {
    if (this.latitude !== null && this.longitude !== null) {
      this.saved = { lat: this.latitude, lng: this.longitude }
      this.picked = { ...this.saved }
    }

    this.map = L.map(this.$refs.map).setView(
      this.picked ? [this.picked.lat, this.picked.lng] : DEFAULT_VIEW.center,
      this.picked ? 11 : DEFAULT_VIEW.zoom,
    )

    tileLayer().addTo(this.map)

    if (!this.readonly) this.map.on('click', e => this.pick(e.latlng.lat, e.latlng.lng))
    if (this.picked) this.drawArea()
  },

  beforeUnmount() {
    this.map?.remove()
  },

  methods: {
    // tāpat kā serverī — noapaļo līdz ~1 km
    pick(lat, lng) {
      this.error = ''
      this.picked = { lat: Math.round(lat * 100) / 100, lng: Math.round(lng * 100) / 100 }
      this.drawArea()
    },

    // aplis ~1 km apkārt un punkts, kas redzams arī attālinātā kartē
    drawArea() {
      const center = [this.picked.lat, this.picked.lng]
      if (this.area) {
        this.area.eachLayer(layer => layer.setLatLng(center))
      } else {
        this.area = L.featureGroup([
          L.circle(center, { radius: 1000, color: '#3a6b3e', weight: 2, fillOpacity: 0.2 }),
          L.circleMarker(center, { radius: 7, color: '#fff', weight: 2, fillColor: '#3a6b3e', fillOpacity: 1 }),
        ]).addTo(this.map)
      }
    },

    useCurrent() {
      if (!navigator.geolocation) {
        this.error = this.t('location.unsupported')
        return
      }
      this.locating = true
      navigator.geolocation.getCurrentPosition(
        pos => {
          this.locating = false
          this.pick(pos.coords.latitude, pos.coords.longitude)
          this.map.setView([this.picked.lat, this.picked.lng], 12)
        },
        () => {
          this.locating = false
          this.error = this.t('location.denied')
        },
        { enableHighAccuracy: false, timeout: 10000 },
      )
    },

    async save() {
      this.saving = true
      try {
        const { data } = await axios.put('/api/profile/location', {
          latitude: this.picked.lat,
          longitude: this.picked.lng,
        })
        this.saved = { lat: data.latitude, lng: data.longitude }
        showToast(this.t('location.saved'))
      } catch {
        this.error = this.t('dash.genericError')
      } finally {
        this.saving = false
      }
    },

    async remove() {
      this.saving = true
      try {
        await axios.delete('/api/profile/location')
        this.saved = null
        this.picked = null
        this.area?.remove()
        this.area = null
        showToast(this.t('location.removed'))
      } catch {
        this.error = this.t('dash.genericError')
      } finally {
        this.saving = false
      }
    },
  },
}
</script>

<style scoped>
.location-map {
  height: 320px;
  border-radius: 10px;
  border: 1px solid var(--border);
  z-index: 0;
}
</style>
