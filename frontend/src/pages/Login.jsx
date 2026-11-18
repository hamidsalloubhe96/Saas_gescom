import { useCallback, useEffect, useState } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import api, { parseError, storage } from '../api'
import { useAuth } from '../auth'
import AuthShell from '../components/AuthShell'
import Field from '../components/Field'
import Turnstile from '../components/Turnstile'
import { PasswordField } from '../components/ui'

export default function Login() {
  const { user, login } = useAuth()
  const navigate = useNavigate()
  const notice = useLocation().state?.notice
  const [form, setForm] = useState({ tenant: storage.get('tenant') ?? '', email: '', password: '' })
  const [remember, setRemember] = useState(true)
  const [error, setError] = useState(null)
  const [fields, setFields] = useState({})
  const [busy, setBusy] = useState(false)
  // Contrôle anti-robot (Cloudflare Turnstile), affiché seulement si le serveur a une clé de site.
  const [siteKey, setSiteKey] = useState(null)
  const [captcha, setCaptcha] = useState(null)
  const [captchaKey, setCaptchaKey] = useState(0)
  const onToken = useCallback((t) => setCaptcha(t), [])

  useEffect(() => {
    api.get('/public/config').then(({ data }) => setSiteKey(data.turnstile_site_key)).catch(() => {})
  }, [])

  if (user) return <Navigate to="/" replace />

  const set = (e) => setForm({ ...form, [e.target.name]: e.target.value })

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    setFields({})
    if (siteKey && !captcha) {
      setError("Validez d'abord la vérification anti-robot.")
      setBusy(false)
      return
    }
    try {
      await login({ ...form, tenant: form.tenant.trim().toLowerCase(), remember, captcha })
      navigate('/', { replace: true })
    } catch (err) {
      const parsed = parseError(err)
      // 404 = entreprise inconnue : on l'affiche sur le champ concerné.
      if (err.response?.status === 404) setFields({ tenant: 'Entreprise introuvable.' })
      else setFields(parsed.fields)
      setError(err.response?.status === 404 ? null : parsed.message)
      setCaptcha(null)
      setCaptchaKey((k) => k + 1) // un jeton Turnstile ne sert qu'une fois : on affiche un nouveau contrôle
    } finally {
      setBusy(false)
    }
  }

  return (
    <AuthShell
      title="Connexion"
      subtitle="Accédez à l'espace de votre entreprise."
      footer={<>
        <p className="m-0">Pas encore d'espace ? <Link to="/register" className="font-semibold">Créer une entreprise</Link> · <Link to="/demande" className="font-semibold">Demander un accès</Link></p>
        <p className="mb-0 mt-3"><Link to="/bienvenue" className="text-muted no-underline hover:underline">Retour au site</Link></p>
      </>}
    >
      <form onSubmit={submit} noValidate>
        {notice && <div role="status" className="alert alert-ok">{notice}</div>}
        {error && <div role="alert" className="alert">{error}</div>}
        <Field label="Identifiant de l'entreprise" name="tenant" value={form.tenant} onChange={set}
          error={fields.tenant} autoComplete="organization" autoCapitalize="none" required />
        <Field label="Email" name="email" type="email" value={form.email} onChange={set}
          error={fields.email} autoComplete="username" required />
        <PasswordField label="Mot de passe" name="password" value={form.password} onChange={set}
          error={fields.password} autoComplete="current-password" required />
        <Turnstile key={captchaKey} siteKey={siteKey} onToken={onToken} />
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2 text-sm">
          <label className="flex items-center gap-2"><input type="checkbox" className="size-4 accent-[var(--accent)]" checked={remember} onChange={(e) => setRemember(e.target.checked)} /> Se souvenir de moi</label>
          <Link to="/mot-de-passe-oublie" className="font-semibold">Mot de passe oublié ?</Link>
        </div>
        <button className="btn" disabled={busy}>{busy ? 'Connexion…' : 'Se connecter'}</button>
      </form>
    </AuthShell>
  )
}
