// "atcerēties ierīci" atslēgas katram e-pastam šajā pārlūkā
const KEY = 'bookloop_devices'

function read() {
  try {
    return JSON.parse(localStorage.getItem(KEY)) || {}
  } catch {
    return {}
  }
}

export function getDeviceToken(email) {
  return read()[email.trim().toLowerCase()] || undefined
}

export function saveDeviceToken(email, token) {
  const all = read()
  all[email.trim().toLowerCase()] = token
  try { localStorage.setItem(KEY, JSON.stringify(all)) } catch { /* ignorē */ }
}
