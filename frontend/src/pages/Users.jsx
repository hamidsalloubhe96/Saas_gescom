import { useCallback, useEffect, useState } from 'react'
import { AddIcon, RowActions } from '../components/ui'
import api, { parseError } from '../api'
import { useAuth } from '../auth'
import Field from '../components/Field'
import Modal from '../components/Modal'

const EMPTY = { name: '', email: '', password: '', role: 'seller', is_active: true, branch_id: '' }

export default function Users() {
  const { user: me, platform } = useAuth()
  const [users, setUsers] = useState([])
  const [catalog, setCatalog] = useState({ roles: [], permissions: {} })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [editing, setEditing] = useState(null) // null = fermé, {} = création, {id,...} = édition
  const [form, setForm] = useState(EMPTY)
  const [fields, setFields] = useState({})
  const [formError, setFormError] = useState(null)
  const [saving, setSaving] = useState(false)

  const load = useCallback(async () => {
    try {
      const [u, r] = await Promise.all([api.get('/users'), api.get('/roles')])
      setUsers(u.data)
      setCatalog(r.data)
    } catch (err) {
      setError(parseError(err).message)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { load() }, [load])

  const roleOf = (key) => catalog.roles.find((r) => r.key === key)

  function openForm(u) {
    setFields({})
    setFormError(null)
    setForm(u ? { name: u.name, email: u.email, password: '', role: u.role, is_active: u.is_active, branch_id: u.branch_id ?? '' } : EMPTY)
    setEditing(u ?? {})
  }

  async function submit(e) {
    e.preventDefault()
    setSaving(true)
    setFormError(null)
    setFields({})
    try {
      const payload = { ...form, branch_id: form.role === 'admin' || !form.branch_id ? null : Number(form.branch_id) }
      if (editing.id) await api.put(`/users/${editing.id}`, payload)
      else await api.post('/users', payload)
      setEditing(null)
      await load()
    } catch (err) {
      const p = parseError(err)
      setFields(p.fields)
      setFormError(p.message)
    } finally {
      setSaving(false)
    }
  }

  const isSelf = editing?.id === me.id

  return (
    <>
      <header className="page-head page-head-row">
        <div>
          <h1>Utilisateurs</h1>
          <p className="muted">{users.length} compte{users.length > 1 ? 's' : ''} · chaque rôle donne accès à une partie de l'application</p>
        </div>
        <button className="btn btn-inline" onClick={() => openForm(null)}><AddIcon className="size-4" aria-hidden="true" /> Nouvel utilisateur</button>
      </header>

      {error && <div role="alert" className="alert">{error}</div>}

      <div className="table-wrap" aria-busy={loading}>
        <table>
          <thead><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Statut</th><th /></tr></thead>
          <tbody>
            {users.map((u) => (
              <tr key={u.id}>
                <td><strong>{u.name}</strong>{u.id === me.id && <span className="muted small"> (vous)</span>}</td>
                <td>{u.email}</td>
                <td>{u.role_label}</td>
                <td><span className={`pill ${u.is_active ? 'pill-on' : 'pill-off'}`}>{u.is_active ? 'Actif' : 'Désactivé'}</span></td>
                <td className="actions"><RowActions name={u.name} onEdit={() => openForm(u)} /></td>
              </tr>
            ))}
            {loading && users.length === 0 && <tr><td colSpan="5" className="empty">Chargement…</td></tr>}
          </tbody>
        </table>
      </div>

      <details className="panel matrix">
        <summary>Voir les droits de chaque rôle</summary>
        <div className="table-wrap flat">
          <table>
            <thead>
              <tr><th>Droit</th>{catalog.roles.map((r) => <th key={r.key} className="center">{r.label}</th>)}</tr>
            </thead>
            <tbody>
              {Object.entries(catalog.permissions).map(([key, label]) => (
                <tr key={key}>
                  <td>{label}</td>
                  {catalog.roles.map((r) => (
                    <td key={r.key} className="center">
                      {r.permissions.includes(key)
                        ? <span aria-label="Autorisé" className="qty-pos">✓</span>
                        : <span aria-label="Refusé" className="muted">—</span>}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </details>

      <Modal open={Boolean(editing)} onClose={() => setEditing(null)} title={editing?.id ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur'}>
        {editing && (
          <form onSubmit={submit} noValidate>
            {formError && <div role="alert" className="alert">{formError}</div>}
            <Field label="Nom *" name="name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} error={fields.name} />
            <Field label="Email *" name="email" type="email" value={form.email} autoComplete="off"
              onChange={(e) => setForm({ ...form, email: e.target.value })} error={fields.email} />
            <Field label={editing.id ? 'Nouveau mot de passe' : 'Mot de passe *'} name="password" type="password" autoComplete="new-password"
              value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} error={fields.password}
              hint={editing.id ? 'Laisser vide pour ne pas le changer (le changer déconnecte l\'utilisateur)' : '8 caractères minimum'} />
            <div className="field">
              <label htmlFor="role">Rôle *</label>
              <select id="role" value={form.role} disabled={isSelf} onChange={(e) => setForm({ ...form, role: e.target.value })}>
                {catalog.roles.map((r) => <option key={r.key} value={r.key}>{r.label}</option>)}
              </select>
              <p className="field-hint">{isSelf ? 'Vous ne pouvez pas changer votre propre rôle.' : roleOf(form.role)?.description}</p>
            </div>
            {(platform.branches ?? []).length > 1 && form.role !== 'admin' && (
              <div className="field">
                <label htmlFor="branch">Point de vente</label>
                <select id="branch" value={form.branch_id} onChange={(e) => setForm({ ...form, branch_id: e.target.value })}>
                  <option value="">Toutes (siège)</option>
                  {platform.branches.map((b) => <option key={b.id} value={b.id}>{b.name}{b.is_main ? ' (siège)' : ''}</option>)}
                </select>
                <p className="field-hint">Un utilisateur rattaché à un point de vente ne voit que ses ventes, sa caisse et son stock.</p>
              </div>
            )}
            <label className="check">
              <input type="checkbox" checked={form.is_active} disabled={isSelf}
                onChange={(e) => setForm({ ...form, is_active: e.target.checked })} /> Compte actif
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-secondary" onClick={() => setEditing(null)}>Annuler</button>
              <button className="btn" disabled={saving}>{saving ? 'Enregistrement…' : 'Enregistrer'}</button>
            </div>
          </form>
        )}
      </Modal>
    </>
  )
}
