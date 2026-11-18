import { useState } from 'react'
import { Link } from 'react-router-dom'
import api, { parseError } from '../api'
import AuthShell from '../components/AuthShell'
import Field from '../components/Field'

/** Page publique « Demander un accès » : le formulaire arrive dans la console (Demandes d'inscription). */
export default function RequestAccess() {
  const [form, setForm] = useState({ name: '', email: '', phone: '', company: '', country: '', message: '', website: '' })
  const [fields, setFields] = useState({})
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)
  const [sent, setSent] = useState(false)

  const set = (e) => setForm({ ...form, [e.target.name]: e.target.value })

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    setFields({})
    try {
      await api.post('/signup-requests', form)
      setSent(true)
    } catch (err) {
      const p = parseError(err)
      setFields(p.fields)
      setError(p.message)
    } finally {
      setBusy(false)
    }
  }

  if (sent) {
    return (
      <AuthShell title="Demande envoyée" subtitle="Merci ! Nous vous recontactons très vite." footer={<Link to="/login">Retour à la connexion</Link>}>
        <p className="muted">Vous recevrez vos accès par e-mail une fois votre espace créé.</p>
      </AuthShell>
    )
  }

  return (
    <AuthShell title="Demander un accès" subtitle="Dites-nous qui vous êtes, nous créons votre espace."
      footer={<>Déjà un espace ? <Link to="/login">Se connecter</Link></>}>
      <form onSubmit={submit} noValidate>
        {error && <div role="alert" className="alert">{error}</div>}
        <Field label="Nom de l'entreprise *" name="company" value={form.company} onChange={set} error={fields.company} autoComplete="organization" required />
        <Field label="Votre nom *" name="name" value={form.name} onChange={set} error={fields.name} autoComplete="name" required />
        <div className="row">
          <Field label="E-mail *" name="email" type="email" value={form.email} onChange={set} error={fields.email} autoComplete="email" required />
          <Field label="Téléphone" name="phone" value={form.phone} onChange={set} error={fields.phone} autoComplete="tel" />
        </div>
        <Field label="Pays" name="country" value={form.country} onChange={set} error={fields.country} autoComplete="country-name" />
        <div className="field">
          <label htmlFor="message">Message</label>
          <textarea id="message" name="message" rows="3" value={form.message} onChange={set} maxLength={1000} />
          <p className="field-error">{fields.message || ' '}</p>
        </div>
        {/* Champ piège : invisible pour une personne, rempli par les robots. */}
        <div aria-hidden="true" style={{ position: 'absolute', left: '-9999px' }}>
          <label htmlFor="website">Ne pas remplir</label>
          <input id="website" name="website" tabIndex={-1} autoComplete="off" value={form.website} onChange={set} />
        </div>
        <button className="btn" disabled={busy}>{busy ? 'Envoi…' : 'Envoyer ma demande'}</button>
      </form>
    </AuthShell>
  )
}
