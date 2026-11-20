import { useCallback, useEffect, useState } from 'react'
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router-dom'
import api, { parseError } from '../api'
import { useAuth } from '../auth'
import AuthShell from '../components/AuthShell'
import Field from '../components/Field'
import Turnstile from '../components/Turnstile'
import { PasswordField, PharmacyIcon, ProductionIcon, ProductsIcon } from '../components/ui'

const ACTIVITIES = [
  { value: 'general', label: 'Commerce général', hint: 'Supermarché, boutique, quincaillerie', Icon: ProductsIcon },
  { value: 'pharmacy', label: 'Pharmacie', hint: 'Lots, péremptions, équivalents', Icon: PharmacyIcon },
  { value: 'bakery', label: 'Boulangerie', hint: 'Recettes, production, invendus', Icon: ProductionIcon },
]

const slugify = (s) =>
  s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
    .replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/^[0-9_]+/, '').slice(0, 30)

export default function Register() {
  const { user, register } = useAuth()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const [config, setConfig] = useState(null) // réglages publics : inscriptions ouvertes ? vérification d'e-mail ? anti-robot ?
  const [form, setForm] = useState({
    company: '', slug: '', name: '', email: '', password: '', code: '', business_type: ['pharmacy', 'bakery'].includes(params.get('activite')) ? params.get('activite') : 'general',
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

  // Pas de champ de confirmation : l'œil permet de vérifier la saisie ; l'API attend tout de même la confirmation.
  const payload = () => ({ ...form, password_confirmation: form.password, 'cf-turnstile-response': captcha })

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    setFields({})
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
    <AuthShell wide
      title="Créer votre entreprise"
      subtitle={`Votre espace est prêt en quelques secondes${config?.trial_days ? ` · ${config.trial_days} jours d'essai gratuit, sans carte bancaire` : ''}.`}
      footer={<>Déjà inscrit ? <Link to="/login">Se connecter</Link></>}
    >
      <form onSubmit={submit} noValidate>
        {error && <div role="alert" className="alert">{error}</div>}
        <div className="grid gap-x-6 gap-y-4 sm:grid-cols-2 sm:gap-y-0">
          <fieldset className="min-w-0 border-0 p-0">
            <legend className="mb-3 text-xs font-semibold text-muted">Entreprise</legend>
            <Field label="Nom de l'entreprise" name="company" value={form.company} onChange={set}
              error={fields.company} autoComplete="organization" required />
            <Field label="Identifiant" name="slug" value={form.slug} onChange={set} error={fields.slug}
              hint="Sert à vous connecter : lettres minuscules, chiffres, _ (3 à 30)"
              autoCapitalize="none" required />
          </fieldset>
          <fieldset className="min-w-0 border-0 p-0">
            <legend className="mb-3 text-xs font-semibold text-muted">Votre compte</legend>
            <Field label="Votre nom" name="name" value={form.name} onChange={set}
              error={fields.name} autoComplete="name" required />
            <Field label="E-mail" name="email" type="email" value={form.email} onChange={set}
              error={fields.email} autoComplete="email" required />
          </fieldset>
        </div>
        <fieldset className="mb-4 min-w-0 border-0 p-0">
          <legend className="mb-2 text-xs font-semibold text-muted">Type d'activité</legend>
          <div role="radiogroup" aria-label="Type d'activité" className="grid gap-2 sm:grid-cols-3">
            {ACTIVITIES.map((a) => {
              const on = form.business_type === a.value
              return (
                <button key={a.value} type="button" role="radio" aria-checked={on}
                  onClick={() => setForm({ ...form, business_type: a.value })}
                  className={`flex items-center gap-3 rounded-xl border px-3 py-2.5 text-left transition-colors ${on ? 'border-accent bg-accent-soft' : 'border-line hover:bg-surface-2'}`}>
                  <a.Icon className="size-5 shrink-0 text-accent" aria-hidden="true" />
                  <span className="min-w-0"><span className="block text-sm font-semibold">{a.label}</span><span className="block text-xs text-muted">{a.hint}</span></span>
                </button>
              )
            })}
          </div>
          {fields.business_type && <p className="field-error">{fields.business_type}</p>}
        </fieldset>
        <PasswordField label="Mot de passe" name="password" value={form.password} onChange={set} meter
          error={fields.password} hint="8 caractères minimum" autoComplete="new-password" required />
        <Turnstile siteKey={config?.turnstile_site_key} onToken={onToken} />
        <button className="btn mt-1" disabled={busy || !config}>{busy ? 'Envoi…' : config?.email_verification ? 'Continuer' : "Créer mon espace"}</button>
      </form>
    </AuthShell>
  )
}
