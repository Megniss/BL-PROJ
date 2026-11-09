const CACHE = 'bookloop-v3'
const STATIC = ['/', '/manifest.json']

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(cache => cache.addAll(STATIC)))
  self.skipWaiting()
})

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    )
  )
  self.clients.claim()
})

// kešo tikai savus failus, api, bildes un kartes laiž tieši uz tīklu
self.addEventListener('fetch', e => {
  const url = new URL(e.request.url)
  if (
    e.request.method !== 'GET' ||
    url.origin !== self.location.origin ||
    url.pathname.startsWith('/api/') ||
    url.searchParams.has('signature')
  ) return

  e.respondWith(
    fetch(e.request)
      .then(res => {
        if (res.ok) {
          const copy = res.clone()
          caches.open(CACHE).then(cache => cache.put(e.request, copy))
        }
        return res
      })
      .catch(async () => {
        // bez tīkla ņem no keša, lapām der sākumlapa (SPA)
        const cached = await caches.match(e.request)
        if (cached) return cached
        if (e.request.mode === 'navigate') {
          const home = await caches.match('/')
          if (home) return home
        }
        return Response.error()
      })
  )
})
