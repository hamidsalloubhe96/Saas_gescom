import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import api, { storage } from './api'

const AuthContext = createContext(null)

/** Tout ce que la plateforme joint à /login et /me en plus de l'utilisateur (abonnement, annonces, assistance…). */
const platformPart = ({ token, user, tenant, company, ...rest }) => rest // eslint-disable-line no-unused-vars

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [tenant, setTenant] = useState(() => storage.get('tenant'))
  const [company, setCompany] = useState(null)
  const [platform, setPlatform] = useState({})
  const [loading, setLoading] = useState(() => Boolean(storage.get('token')))

  const clear = useCallback(() => {
    storage.remove('token')
    setUser(null)
    setPlatform({})
  }, [])

  const apply = useCallback((data) => {
    setUser(data.user)
    setCompany(data.company ?? null)
    setPlatform(platformPart(data))
  }, [])

  // Au chargement : si un jeton existe, on récupère l'utilisateur courant.
  useEffect(() => {
    if (!storage.get('token')) return
    api.get('/me')
      .then(({ data }) => apply(data))
      .catch(clear)
      .finally(() => setLoading(false))
  }, [clear, apply])

  useEffect(() => {
    window.addEventListener('auth:expired', clear)
    return () => window.removeEventListener('auth:expired', clear)
  }, [clear])

  // L'abonnement vient de se terminer pendant la session : on recharge le contexte (drapeau « blocked »).
  useEffect(() => {
    const onExpired = () => { api.get('/me').then(({ data }) => apply(data)).catch(() => {}) }
    window.addEventListener('subscription:expired', onExpired)
    return () => window.removeEventListener('subscription:expired', onExpired)
  }, [apply])

  /** Recharge l'utilisateur et le contexte plateforme (après un paiement, un changement de mot de passe…). */
  const refresh = useCallback(async () => {
    const { data } = await api.get('/me')
    apply(data)
  }, [apply])

  const start = useCallback((data) => {
    storage.set('token', data.token)
    storage.set('tenant', data.tenant)
    setTenant(data.tenant)
    apply(data)
  }, [apply])

  const login = useCallback(async ({ tenant: tenantId, email, password }) => {
    const { data } = await api.post('/login', { email, password }, { tenant: tenantId })
    start(data)
  }, [start])

  const register = useCallback(async (form) => {
    const { data } = await api.post('/register-tenant', form)
    storage.set('tenant', data.tenant)
    setTenant(data.tenant)
    storage.set('token', data.token)
    const me = await api.get('/me', { tenant: data.tenant })
    start({ ...me.data, token: data.token, tenant: data.tenant })
    return data
  }, [start])

  /** Connexion d'assistance : échange le code à usage unique donné par la console contre un jeton. */
  const exchangeImpersonation = useCallback(async (code) => {
    const { data } = await api.post('/impersonation/exchange', { code })
    storage.set('token', data.token)
    storage.set('tenant', data.tenant)
    setTenant(data.tenant)
    const me = await api.get('/me', { tenant: data.tenant })
    apply(me.data)
    return data
  }, [apply])

  const logout = useCallback(async () => {
    try { await api.post('/logout') } catch { /* le jeton est de toute façon effacé */ }
    clear()
  }, [clear])

  // Les droits viennent du serveur (rôle de l'utilisateur) ; le serveur les re-vérifie à chaque requête.
  const can = useCallback((permission) => Boolean(user?.permissions?.includes(permission)), [user])

  const value = useMemo(
    () => ({ user, tenant, company, platform, loading, login, register, logout, can, refresh, exchangeImpersonation }),
    [user, tenant, company, platform, loading, login, register, logout, can, refresh, exchangeImpersonation],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth() {
  return useContext(AuthContext)
}
