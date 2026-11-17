import axios from 'axios'

// kopīgās čata funkcijas abiem čatiem (Messages.vue un Support.vue)
// chat atslēga: "user:{id}" vai "support:{id}"

// ziņa ar tekstu un/vai bildi iet kā FormData
export function messageForm(body, image, extra = {}) {
  const form = new FormData()
  for (const [key, value] of Object.entries(extra)) form.append(key, value)
  if (body) form.append('body', body)
  if (image) form.append('image', image)
  return form
}

// es rakstu - serverī tas dzīvo 5 sekundes
export async function sendTyping(chat) {
  try {
    await axios.post('/api/typing', { chat })
  } catch { /* nav svarīgi */ }
}

// vai otrs raksta
export async function fetchTyping(chat) {
  try {
    const { data } = await axios.get('/api/typing', { params: { chat } })
    return !!data.typing
  } catch {
    return false
  }
}

// pārbaude ik pēc dažām sekundēm var atrast ziņu ātrāk par sūtīšanas atbildi
export function addNew(list, fresh) {
  const ids = new Set(list.map(m => m.id))
  list.push(...fresh.filter(m => !ids.has(m.id)))
}

// cik bieži pārbauda jaunas ziņas un "raksta..."
export const POLL_MS = 3000
