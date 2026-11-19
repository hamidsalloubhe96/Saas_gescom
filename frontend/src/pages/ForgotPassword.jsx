import { useCallback, useEffect, useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import api, { parseError, storage } from '../api'
import { useAuth } from '../auth'
import AuthShell from '../components/AuthShell'
import Field from '../components/Field'
import Turnstile from '../components/Turnstile'
import { PasswordField } from '../components/ui'

/** Mot de passe oublié : 1) identifiant + e-mail -> code par e-mail, 2) code + nouveau mot de passe. */
export default function ForgotPassword() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const [step, setStep] = useState('request')
  const [form, setForm] = useState({ tenant: storage.get('tenant') ?? '', email: '', code: '', password: '', password_confirmation: '' })
  const [info, setInfo] = useState(null)
  const [error, setError] = useState(null)
  const [fields, setFields] = useState({})
  const [busy, setBusy] = useState(false)
  const [wait, setWait] = useState(0) // secondes avant de pouvoir renvoyer un code
  const [siteKey, setSiteKey] = useState(null)
  const [captcha, setCaptcha] = useState(null)
  const [captchaKey, setCaptchaKey] = useState(0)
  const onToken = useCallback((t) => setCaptcha(t), [])

  useEffect(() => {
    api.get('/public/config').then(({ data }) => setSiteKey(data.turnstile_site_key)).catch(() => {})
  }, [])

  useEffect(() => {
    if (wait <= 0) return undefined
    const t = setTimeout(() => setWait((w) => w - 1), 1000)
    return () => clearTimeout(t)
  }, [wait])

  if (user) return <Navigate to="/" replace />

  const set = (e) => setForm({ ...form, [e.target.name]: e.target.value })
  const tenant = () => form.tenant.trim().toLowerCase().replace(/\s+/g, '_')

  async function run(fn) {
    setBusy(true)
    setError(null)
    setFields({})
    try {
      await fn()
    } catch (err) {
      const p = parseError(err)
      if (err.response?.status === 404) setFields({ tenant: 'Entreprise introuvable.' })
      else {
        setFields(p.fields)
        setError(Object.keys(p.fields).length ? null : p.message)
      }
    } finally {
      setBusy(false)
    }
  }

  const sendCode = () => run(async () => {
    if (siteKey && !captcha) { setError("Validez d'abord la vérification anti-robot."); return }
    try {
      const { data } = await api.post('/password/forgot', { email: form.email, 'cf-turnstile-response': captcha }, { tenant: tenant() })
      setInfo(data.message)
      setWait(60)
      setStep('reset')
    } finally {
      setCaptcha(null)
      setCaptchaKey((k) => k + 1) // un jeton Turnstile ne sert qu'une fois
    }
  })

  const submitReset = (e) => {
    e.preventDefault()
    if (form.password !== form.password_confirmation) {
      setFields({ password: 'Les mots de passe ne correspondent pas.' })
      return
    }
    run(async () => {
      await api.post('/password/reset', { email: form.email, code: form.code, password: form.password, password_confirmation: form.password_confirmation }, { tenant: tenant() })
      storage.set('tenant', tenant())
      navigate('/login', { replace: true, state: { notice: 'Mot de passe modifié. Connectez-vous avec le nouveau.' } })
    })
  }

  if (step === 'reset') {
    return (
      <AuthShell title="Nouveau mot de passe" subtitle={info ?? 'Saisissez le code reçu par e-mail.'}
        footer={<button type="button" className="link-btn" onClick={() => { setStep('request'); setError(null); setFields({}) }}>← Changer d'e-mail</button>}>
        <form onSubmit={submitReset} noValidate>
          {error && <div role="alert" className="alert">{error}</div>}
          <Field label="Code à 6 chiffres" name="code" value={form.code} onChange={set} error={fields.code}
            inputMode="numeric" maxLength={6} autoComplete="one-time-code" autoFocus required />
          <PasswordField label="Nouveau mot de passe" name="password" value={form.password} onChange={set} meter
            error={fields.password} hint="8 caractères minimum" autoComplete="new-password" required />
          <PasswordField label="Confirmation" name="password_confirmation" value={form.password_confirmation}
            onChange={set} autoComplete="new-password" required />
          <button className="btn" disabled={busy || form.code.length !== 6}>{busy ? 'Enregistrement…' : 'Changer le mot de passe'}</button>
          <Turnstile key={`r${captchaKey}`} siteKey={siteKey} onToken={onToken} />
          <p className="auth-footer">
            <button type="button" className="link-btn" disabled={wait > 0 || busy} onClick={sendCode}>
              {wait > 0 ? `Renvoyer le code (${wait} s)` : 'Renvoyer le code'}
            </button>
          </p>
        </form>
      </AuthShell>
    )
  }

  return (
    <AuthShell title="Mot de passe oublié" subtitle="Nous vous envoyons un code à 6 chiffres par e-mail."
      footer={<Link to="/login">← Retour à la connexion</Link>}>
      <form onSubmit={(e) => { e.preventDefault(); sendCode() }} noValidate>
        {error && <div role="alert" className="alert">{error}</div>}
        <Field label="Identifiant de l'entreprise" name="tenant" value={form.tenant} onChange={set}
          error={fields.tenant} autoCapitalize="none" required />
        <Field label="Email" name="email" type="email" value={form.email} onChange={set}
          error={fields.email} autoComplete="username" required />
        <Turnstile key={captchaKey} siteKey={siteKey} onToken={onToken} />
        <button className="btn" disabled={busy}>{busy ? 'Envoi…' : 'Envoyer le code'}</button>
      </form>
    </AuthShell>
  )
}
