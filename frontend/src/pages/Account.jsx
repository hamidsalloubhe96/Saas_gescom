import { useState } from 'react'
import api, { parseError } from '../api'
import { useAuth } from '../auth'
import Field from '../components/Field'
import InstallApp from '../components/InstallApp'
import { PasswordField } from '../components/ui'
import { useBusiness } from '../lib/business'

/** Mon compte : nom, e-mail et mot de passe de l'utilisateur connecté. */
export default function Account() {
  const { user, refresh, can, setBusinessType, modules, setModules } = useAuth()
  const { type } = useBusiness()
  const [typeBusy, setTypeBusy] = useState(false)
  const [typeMsg, setTypeMsg] = useState(null)

  async function toggleModule(key, on, messages) {
    setTypeBusy(true); setTypeMsg(null)
    try {
      const { data } = await api.put('/settings/modules', { [key]: on })
      setModules(data.modules)
      setTypeMsg(messages[on ? 0 : 1])
    } catch (err) {
      setTypeMsg(parseError(err).message)
    } finally { setTypeBusy(false) }
  }

  async function changeType(e) {
    const value = e.target.value
    setTypeBusy(true); setTypeMsg(null)
    try {
      await api.put('/settings/business-type', { business_type: value })
      setBusinessType(value)
      setTypeMsg({ pharmacy: 'Mode pharmacie activé.', bakery: 'Mode boulangerie activé.', hardware: 'Mode quincaillerie activé.', wholesale: 'Mode grossiste activé.', services: 'Mode services activé.', butcher: 'Mode boucherie activé.' }[value] ?? 'Mode commerce général activé.')
    } catch (err) {
      setTypeMsg(parseError(err).message)
    } finally { setTypeBusy(false) }
  }

  const [profile, setProfile] = useState({ name: user.name, email: user.email, current_password: '' })
  const [pf, setPf] = useState({})
  const [pMsg, setPMsg] = useState(null)
  const [pErr, setPErr] = useState(null)
  const [pBusy, setPBusy] = useState(false)
  const emailChanged = profile.email.trim().toLowerCase() !== user.email.toLowerCase()

  const [pwd, setPwd] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [wf, setWf] = useState({})
  const [wMsg, setWMsg] = useState(null)
  const [wErr, setWErr] = useState(null)
  const [wBusy, setWBusy] = useState(false)

  async function saveProfile(e) {
    e.preventDefault()
    setPBusy(true); setPErr(null); setPMsg(null); setPf({})
    try {
      await api.put('/profile', { name: profile.name, email: profile.email, current_password: emailChanged ? profile.current_password : undefined })
      await refresh()
      setProfile((p) => ({ ...p, current_password: '' }))
      setPMsg('Informations enregistrées.')
    } catch (err) {
      const p = parseError(err)
      setPf(p.fields); setPErr(Object.keys(p.fields).length ? null : p.message)
    } finally { setPBusy(false) }
  }

  async function savePassword(e) {
    e.preventDefault()
    setWBusy(true); setWErr(null); setWMsg(null); setWf({})
    try {
      await api.post('/password', pwd)
      await refresh()
      setPwd({ current_password: '', password: '', password_confirmation: '' })
      setWMsg('Mot de passe modifié. Vos autres appareils ont été déconnectés.')
    } catch (err) {
      const p = parseError(err)
      setWf(p.fields); setWErr(Object.keys(p.fields).length ? null : p.message)
    } finally { setWBusy(false) }
  }

  return (
    <div className="mx-auto w-full max-w-xl">
      <header className="page-head">
        <h1>Mon compte</h1>
        <p className="muted">{user.email} · {user.role_label}</p>
      </header>
      <InstallApp className="mb-4" />

      {can('users.manage') && (
        <section className="panel">
          <h2>Type d'activité</h2>
          <div className="field">
            <label htmlFor="business_type">Votre activité</label>
            <select id="business_type" value={type} onChange={changeType} disabled={typeBusy}>
              <option value="general">Commerce général</option>
              <option value="hardware">Quincaillerie</option>
              <option value="butcher">Boucherie et produits au poids</option>
              <option value="wholesale">Grossiste et distribution</option>
              <option value="services">Services et prestations</option>
              <option value="pharmacy">Pharmacie</option>
              <option value="bakery">Boulangerie</option>
            </select>
            <p className="field-hint">{typeMsg ?? 'Adapte le menu et le vocabulaire de l\'application.'}</p>
          </div>
          <h3 className="mt-4 text-sm font-semibold">Modules</h3>
          <label className="check">
            <input type="checkbox" checked={(modules ?? []).includes('expiry')} disabled={typeBusy}
              onChange={(e) => toggleModule('expiry', e.target.checked, ['Suivi des péremptions activé : « Péremptions » apparaît dans le menu.', 'Suivi des péremptions désactivé.'])} /> Suivre les dates de péremption (lots, alertes) : charcuterie, produits frais, pharmacie
          </label>
          <label className="check">
            <input type="checkbox" checked={(modules ?? []).includes('production')} disabled={typeBusy}
              onChange={(e) => toggleModule('production', e.target.checked, ['Fabrication activée : « Production » apparaît dans le menu et « Fabriqué » sur les fiches produits.', 'Fabrication désactivée : vos recettes sont conservées.'])} /> Fabriquer sur place (recettes, production, invendus) : boulangerie, traiteur
          </label>
        </section>
      )}

      <section className="panel">
        <h2>Informations</h2>
        <form onSubmit={saveProfile} noValidate>
          {pErr && <div role="alert" className="alert">{pErr}</div>}
          {pMsg && <div role="status" className="alert alert-ok">{pMsg}</div>}
          <Field label="Nom" name="name" value={profile.name} onChange={(e) => setProfile({ ...profile, name: e.target.value })} error={pf.name} autoComplete="name" required />
          <Field label="E-mail de connexion" name="email" type="email" value={profile.email} onChange={(e) => setProfile({ ...profile, email: e.target.value })} error={pf.email} autoComplete="username" required />
          {emailChanged && (
            <PasswordField label="Mot de passe actuel" name="profile_password" value={profile.current_password} autoComplete="current-password"
              onChange={(e) => setProfile({ ...profile, current_password: e.target.value })} error={pf.current_password} hint="Demandé pour confirmer le changement d’e-mail." required />
          )}
          <button className="btn btn-inline" disabled={pBusy}>{pBusy ? 'Enregistrement…' : 'Enregistrer'}</button>
        </form>
      </section>

      <section className="panel">
        <h2>Mot de passe</h2>
        <form onSubmit={savePassword} noValidate>
          {wErr && <div role="alert" className="alert">{wErr}</div>}
          {wMsg && <div role="status" className="alert alert-ok">{wMsg}</div>}
          <PasswordField label="Mot de passe actuel" name="current_password" value={pwd.current_password} autoComplete="current-password"
            onChange={(e) => setPwd({ ...pwd, current_password: e.target.value })} error={wf.current_password} required />
          <PasswordField label="Nouveau mot de passe" name="password" value={pwd.password} autoComplete="new-password" meter
            onChange={(e) => setPwd({ ...pwd, password: e.target.value })} error={wf.password} hint="8 caractères minimum" required />
          <PasswordField label="Confirmation" name="password_confirmation" value={pwd.password_confirmation} autoComplete="new-password"
            onChange={(e) => setPwd({ ...pwd, password_confirmation: e.target.value })} required />
          <button className="btn btn-inline" disabled={wBusy}>{wBusy ? 'Enregistrement…' : 'Changer le mot de passe'}</button>
        </form>
      </section>
    </div>
  )
}
