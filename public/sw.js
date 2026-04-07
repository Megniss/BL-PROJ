const CACHE = 'bookloop-v1'
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

// api nekešo, pārējo mēģina no tīkla, ja neizdodas tad no keša
self.addEventListener('fetch', e => {
  if (e.request.url.includes('/api/')) return

  e.respondWith(
    fetch(e.request).then(res => {
      const copy = res.clone()
      caches.open(CACHE).then(cache => cache.put(e.request, copy))
      return res
    }).catch(() => caches.match(e.request))
  )
})
