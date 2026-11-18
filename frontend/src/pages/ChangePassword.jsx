import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api, { parseError } from '../api'
import { useAuth } from '../auth'
import AuthShell from '../components/AuthShell'
import Field from '../components/Field'

/** Changement de mot de passe : imposé après un mot de passe provisoire donné par la plateforme. */
export default function ChangePassword() {
  const { user, refresh, logout } = useAuth()
  const navigate = useNavigate()
  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [fields, setFields] = useState({})
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)
  const forced = Boolean(user?.must_change_password)

  const set = (e) => setForm({ ...form, [e.target.name]: e.target.value })

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    setFields({})
    try {
      await api.post('/password', form)
      await refresh()
      navigate('/', { replace: true })
    } catch (err) {
      const p = parseError(err)
      setFields(p.fields)
      setError(p.message)
      setBusy(false)
    }
  }

  return (
    <AuthShell
      title="Nouveau mot de passe"
      subtitle={forced ? 'Votre mot de passe est provisoire : choisissez-en un nouveau pour continuer.' : 'Choisissez un nouveau mot de passe.'}
      footer={<button type="button" className="link-btn" onClick={logout}>Se déconnecter</button>}
    >
      <form onSubmit={submit} noValidate>
        {error && <div role="alert" className="alert">{error}</div>}
        <Field label="Mot de passe actuel" name="current_password" type="password" autoComplete="current-password"
          value={form.current_password} onChange={set} error={fields.current_password} required />
        <Field label="Nouveau mot de passe" name="password" type="password" autoComplete="new-password"
          value={form.password} onChange={set} error={fields.password} hint="8 caractères minimum" required />
        <Field label="Confirmation" name="password_confirmation" type="password" autoComplete="new-password"
          value={form.password_confirmation} onChange={set} required />
        <button className="btn" disabled={busy}>{busy ? 'Enregistrement…' : 'Enregistrer'}</button>
      </form>
    </AuthShell>
  )
}
