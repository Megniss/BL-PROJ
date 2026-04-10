import { reactive } from 'vue'

export const toasts = reactive([])
let _id = 0

// type: 'success' | 'error' | 'info'
export function showToast(message, type = 'success', duration = 3200) {
  const id = ++_id
  toasts.push({ id, message, type })
  setTimeout(() => {
    const i = toasts.findIndex(t => t.id === id)
    if (i !== -1) toasts.splice(i, 1)
  }, duration)
}
