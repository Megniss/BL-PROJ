import L from 'leaflet'

const OSM_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a>'

// MapTiler, ja .env ir atslēga, citādi OSM
export function tileLayer() {
  const key = import.meta.env.VITE_MAPTILER_KEY

  if (!key) {
    return L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 15,
      attribution: OSM_ATTRIBUTION,
    })
  }

  return L.tileLayer(`https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=${key}`, {
    tileSize: 512,
    zoomOffset: -1,
    minZoom: 1,
    maxZoom: 15,
    crossOrigin: true,
    attribution: '<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a> ' + OSM_ATTRIBUTION,
  })
}
