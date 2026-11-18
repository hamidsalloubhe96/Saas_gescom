import axios from 'axios'

export const storage = {
  get: (k) => {
    try { return localStorage.getItem(k) } catch { return null }
  },
  set: (k, v) => {
    try { localStorage.setItem(k, v) } catch { /* ignore */ }
  },
  remove: (k) => {
    try { localStorage.removeItem(k) } catch { /* ignore */ }
  },
}

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api',
  headers: { Accept: 'application/json' },
})

// Chaque requête porte le jeton Sanctum et l'identifiant de l'entreprise (tenant).
api.interceptors.request.use((config) => {
  const token = storage.get('token')
  const tenant = config.tenant ?? storage.get('tenant')
  if (token) config.headers.Authorization = `Bearer ${token}`
  if (tenant) config.headers['X-Tenant'] = tenant
  // Point de vente choisi par un utilisateur du siège (le serveur impose le sien aux utilisateurs d'un point de vente).
  const branch = storage.get('branch')
  if (branch && token) config.headers['X-Branch'] = branch
  return config
})

// Jeton expiré ou révoqué : on nettoie et on renvoie vers la connexion.
api.interceptors.response.use(
  (r) => r,
  (error) => {
    const code = error.response?.data?.code
    if (error.response?.status === 503 && code === 'maintenance') {
      window.dispatchEvent(new CustomEvent('platform:maintenance', { detail: error.response.data.message }))
    }
    if (error.response?.status === 403 && code === 'subscription_expired') {
      window.dispatchEvent(new Event('subscription:expired'))
    }
    const url = error.config?.url ?? ''
    if (error.response?.status === 401 && !url.includes('/login')) {
      storage.remove('token')
      window.dispatchEvent(new Event('auth:expired'))
    }
    return Promise.reject(error)
  },
)

/** Extrait un message lisible (et les erreurs par champ) d'une erreur axios. */
export function parseError(error) {
  const data = error.response?.data
  if (data?.errors) {
    const fields = Object.fromEntries(
      Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]),
    )
    return { message: 'Certains champs sont invalides.', fields }
  }
  if (data?.message) return { message: data.message, fields: {} }
  if (!error.response) return { message: 'Serveur injoignable. Vérifiez votre connexion.', fields: {} }
  return { message: 'Une erreur est survenue.', fields: {} }
}

export default api
