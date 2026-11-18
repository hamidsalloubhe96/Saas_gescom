import { useState } from 'react'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { parseError, storage } from '../api'
import { useAuth } from '../auth'
import AuthShell from '../components/AuthShell'
import Field from '../components/Field'
import { PasswordField } from '../components/ui'

export default function Login() {
  const { user, login } = useAuth()
  const navigate = useNavigate()
  const notice = useLocation().state?.notice
  const [form, setForm] = useState({ tenant: storage.get('tenant') ?? '', email: '', password: '' })
  const [error, setError] = useState(null)
  const [fields, setFields] = useState({})
  const [busy, setBusy] = useState(false)

  if (user) return <Navigate to="/" replace />

  const set = (e) => setForm({ ...form, [e.target.name]: e.target.value })

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    setFields({})
    try {
      await login({ ...form, tenant: form.tenant.trim().toLowerCase() })
      navigate('/', { replace: true })
    } catch (err) {
      const parsed = parseError(err)
      // 404 = entreprise inconnue : on l'affiche sur le champ concerné.
      if (err.response?.status === 404) setFields({ tenant: 'Entreprise introuvable.' })
      else setFields(parsed.fields)
      setError(err.response?.status === 404 ? null : parsed.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <AuthShell
      title="Connexion"
      subtitle="Accédez à l'espace de votre entreprise."
      footer={<>Pas encore d'espace ? <Link to="/register">Créer une entreprise</Link> · <Link to="/demande">Demander un accès</Link>
        <span className="legal-foot"><Link to="/legal/terms">Conditions</Link> · <Link to="/legal/privacy">Confidentialité</Link></span></>}
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
        <p className="auth-footer" style={{ margin: '0 0 .75rem', textAlign: 'right' }}><Link to="/mot-de-passe-oublie">Mot de passe oublié ?</Link></p>
        <button className="btn" disabled={busy}>{busy ? 'Connexion…' : 'Se connecter'}</button>
      </form>
    </AuthShell>
  )
}
