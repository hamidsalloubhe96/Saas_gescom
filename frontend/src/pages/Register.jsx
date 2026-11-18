import { useCallback, useEffect, useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import api, { parseError } from '../api'
import { useAuth } from '../auth'
import AuthShell from '../components/AuthShell'
import Field from '../components/Field'
import Turnstile from '../components/Turnstile'

const slugify = (s) =>
  s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
    .replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/^[0-9_]+/, '').slice(0, 30)

export default function Register() {
  const { user, register } = useAuth()
  const navigate = useNavigate()
  const [config, setConfig] = useState(null) // réglages publics : inscriptions ouvertes ? vérification d'e-mail ? anti-robot ?
  const [form, setForm] = useState({
    company: '', slug: '', name: '', email: '', password: '', password_confirmation: '', code: '',
  })
  const [slugEdited, setSlugEdited] = useState(false)
  const [step, setStep] = useState('form') // form -> code (si la vérification d'e-mail est activée)
  const [captcha, setCaptcha] = useState(null)
  const [info, setInfo] = useState(null)
  const [error, setError] = useState(null)
  const [fields, setFields] = useState({})
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    api.get('/public/config').then(({ data }) => setConfig(data)).catch(() => setConfig({ signup_open: true }))
  }, [])

  const onToken = useCallback((t) => setCaptcha(t), [])

  if (user) return <Navigate to="/" replace />

  if (config && !config.signup_open) {
    return (
      <AuthShell title="Inscriptions fermées" subtitle="La création libre d'un espace est suspendue pour le moment."
        footer={<Link to="/login">Retour à la connexion</Link>}>
        <p className="muted">Laissez-nous vos coordonnées, nous vous recontactons pour créer votre espace.</p>
        <Link to="/demande" className="btn btn-link">Demander un accès</Link>
      </AuthShell>
    )
  }

  function set(e) {
    const { name, value } = e.target
    const next = { ...form, [name]: value }
    if (name === 'company' && !slugEdited) next.slug = slugify(value)
    if (name === 'slug') {
      setSlugEdited(true)
      next.slug = value.toLowerCase()
    }
    setForm(next)
  }

  const payload = () => ({ ...form, 'cf-turnstile-response': captcha })

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    setFields({})
    if (form.password !== form.password_confirmation) {
      setFields({ password: 'Les mots de passe ne correspondent pas.' })
      setBusy(false)
      return
    }
    if (config?.turnstile_site_key && !captcha) {
      setError('Validez d\'abord la vérification anti-robot.')
      setBusy(false)
      return
    }
    try {
      // Étape 1 : l'e-mail doit être confirmé par un code avant de créer l'espace.
      if (config?.email_verification && step === 'form') {
        const { data } = await api.post('/register-tenant/code', { email: form.email, 'cf-turnstile-response': captcha })
        setInfo(data.message)
        setStep('code')
        setBusy(false)
        return
      }
      await register(payload())
      navigate('/', { replace: true })
    } catch (err) {
      const parsed = parseError(err)
      setFields(parsed.fields)
      setError(parsed.message)
      // Un code refusé : on reste sur l'étape du code ; une autre erreur de champ : retour au formulaire.
      if (step === 'code' && !parsed.fields.code && Object.keys(parsed.fields).length) setStep('form')
    } finally {
      setBusy(false)
    }
  }

  async function resend() {
    setError(null)
    try {
      const { data } = await api.post('/register-tenant/code', { email: form.email, 'cf-turnstile-response': captcha })
      setInfo(data.message)
    } catch (err) {
      setError(parseError(err).message)
    }
  }

  if (step === 'code') {
    return (
      <AuthShell title="Vérifiez votre e-mail" subtitle={info ?? 'Saisissez le code reçu par e-mail.'}
        footer={<button type="button" className="link-btn" onClick={() => { setStep('form'); setInfo(null) }}>← Modifier mes informations</button>}>
        <form onSubmit={submit} noValidate>
          {error && <div role="alert" className="alert">{error}</div>}
          <Field label="Code à 6 chiffres" name="code" value={form.code} onChange={set} error={fields.code}
            inputMode="numeric" maxLength={6} autoComplete="one-time-code" autoFocus required />
          <button className="btn" disabled={busy}>{busy ? 'Création…' : "Créer l'entreprise"}</button>
          <p className="auth-footer"><button type="button" className="link-btn" onClick={resend}>Renvoyer le code</button></p>
        </form>
      </AuthShell>
    )
  }

  return (
    <AuthShell
      title="Créer votre entreprise"
      subtitle={`Votre espace est prêt en quelques secondes${config?.trial_days ? ` · ${config.trial_days} jours d'essai gratuit` : ''}.`}
      footer={<>Déjà inscrit ? <Link to="/login">Se connecter</Link></>}
    >
      <form onSubmit={submit} noValidate>
        {error && <div role="alert" className="alert">{error}</div>}
        <Field label="Nom de l'entreprise" name="company" value={form.company} onChange={set}
          error={fields.company} autoComplete="organization" required />
        <Field label="Identifiant" name="slug" value={form.slug} onChange={set} error={fields.slug}
          hint="3 à 30 caractères : lettres minuscules, chiffres, _ (sert à vous connecter)"
          autoCapitalize="none" required />
        <Field label="Votre nom" name="name" value={form.name} onChange={set}
          error={fields.name} autoComplete="name" required />
        <Field label="Email" name="email" type="email" value={form.email} onChange={set}
          error={fields.email} autoComplete="email" required />
        <div className="row">
          <Field label="Mot de passe" name="password" type="password" value={form.password} onChange={set}
            error={fields.password} hint="8 caractères minimum" autoComplete="new-password" required />
          <Field label="Confirmation" name="password_confirmation" type="password"
            value={form.password_confirmation} onChange={set} autoComplete="new-password" required />
        </div>
        <Turnstile siteKey={config?.turnstile_site_key} onToken={onToken} />
        <button className="btn" disabled={busy || !config}>{busy ? 'Envoi…' : config?.email_verification ? 'Continuer' : "Créer l'entreprise"}</button>
      </form>
    </AuthShell>
  )
}
