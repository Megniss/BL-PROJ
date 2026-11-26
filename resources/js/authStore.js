import { reactive } from 'vue'
import axios from 'axios'

// login glabājas sessionStorage - aizverot logu tas pazūd
// jaunai cilnei to iedod jau atvērtās cilnes (BroadcastChannel)
const channel = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('bookloop_auth') : null

// servera palaišanas id - ja serveris restartēts, vecais login vairs neder
const serverBoot = document.querySelector('meta[name="server-boot"]')?.content || ''

function read(key) {
  try { return sessionStorage.getItem(key) } catch { return null }
}

function write(user, token) {
  try {
    sessionStorage.setItem('bookloop_user', JSON.stringify(user))
    sessionStorage.setItem('bookloop_token', token)
    sessionStorage.setItem('bookloop_boot', serverBoot)
  } catch { /* privātais režīms u.c. */ }
}

function forget() {
  try {
    sessionStorage.removeItem('bookloop_user')
    sessionStorage.removeItem('bookloop_token')
    sessionStorage.removeItem('bookloop_boot')
  } catch { /* ignorē */ }
}

// vecās versijas login no localStorage vairs nevajag
try {
  localStorage.removeItem('bookloop_user')
  localStorage.removeItem('bookloop_token')
} catch { /* ignorē */ }

// serveris restartēts kopš ielogošanās - izlogo
if (read('bookloop_token') && read('bookloop_boot') !== serverBoot) forget()

const savedUser = read('bookloop_user')
const savedToken = read('bookloop_token')

export const authStore = reactive({
  user: savedUser ? JSON.parse(savedUser) : null,
  token: savedToken ? savedToken : null,
})

// ja lietotājs jau bija ielogojies, atjauno headeri
if (authStore.token) {
  axios.defaults.headers.common['Authorization'] = `Bearer ${authStore.token}`
}

function apply(user, token) {
  authStore.user = user
  authStore.token = token
  write(user, token)
  axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
}

function drop() {
  authStore.user = null
  authStore.token = null
  forget()
  delete axios.defaults.headers.common['Authorization']
}

export function setAuth(user, token) {
  apply(user, token)
  channel?.postMessage({ type: 'login', user, token, boot: serverBoot })
}

export function clearAuth() {
  drop()
  channel?.postMessage({ type: 'logout' })
}

export function updateUser(user) {
  updateUserLocal(user)
  channel?.postMessage({ type: 'user', user })
}

export function isLoggedIn() {
  return !!authStore.token
}

// citas cilnes ziņas
channel?.addEventListener('message', ({ data }) => {
  if (data.type === 'ask' && authStore.token) {
    channel.postMessage({ type: 'answer', user: authStore.user, token: authStore.token, boot: serverBoot })
  }
  if (data.type === 'login' && data.boot === serverBoot) apply(data.user, data.token)
  if (data.type === 'logout' && authStore.token) {
    drop()
    window.location.href = '/login'
  }
  if (data.type === 'user' && authStore.token) updateUserLocal(data.user)
})

function updateUserLocal(user) {
  authStore.user = user
  try { sessionStorage.setItem('bookloop_user', JSON.stringify(user)) } catch { /* ignorē */ }
}

// jauna cilne bez login - pajautā atvērtajām cilnēm, vai kāda ir ielogojusies
// ja neviena neatbild (visi logi bija aizvērti), paliek izlogots
export function authReady() {
  if (authStore.token || !channel) return Promise.resolve()

  return new Promise(resolve => {
    const done = () => {
      channel.removeEventListener('message', onAnswer)
      resolve()
    }
    const onAnswer = ({ data }) => {
      if (data.type === 'answer' && data.boot === serverBoot) {
        apply(data.user, data.token)
        clearTimeout(timer)
        done()
      }
    }
    const timer = setTimeout(done, 150)
    channel.addEventListener('message', onAnswer)
    channel.postMessage({ type: 'ask' })
  })
}
