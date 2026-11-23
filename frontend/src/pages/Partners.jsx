import { useCallback, useEffect, useRef, useState } from 'react'
import { AddIcon, RowActions } from '../components/ui'
import ListCards, { ListCard } from '../components/ListCards'
import api, { parseError } from '../api'
import { useAuth } from '../auth'
import Field from '../components/Field'

const EMPTY = {
  name: '', email: '', phone: '', address: '', city: '', tax_id: '', notes: '', is_active: true,
}

/**
 * Liste + formulaire pour un type de tiers (clients, fournisseurs).
 * `extra` décrit le champ propre au type : { name, label, type, hint, format }.
 */
export default function Partners({ resource, title, singular, extra }) {
  const { can } = useAuth()
  const canManage = can(`${resource}.manage`) // customers.manage / suppliers.manage
  const [rows, setRows] = useState([])
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 })
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [query, setQuery] = useState('')
  const [status, setStatus] = useState('')
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState(null)

  const [editing, setEditing] = useState(null) // null = fermé, {} = création, {id,...} = édition
  const [form, setForm] = useState(EMPTY)
  const [fields, setFields] = useState({})
  const [formError, setFormError] = useState(null)
  const [saving, setSaving] = useState(false)
  const dialog = useRef(null)

  // Recherche différée pour ne pas appeler l'API à chaque frappe.
  useEffect(() => {
    const t = setTimeout(() => { setQuery(search.trim()); setPage(1) }, 300)
    return () => clearTimeout(t)
  }, [search])

  const load = useCallback(async () => {
    setLoading(true)
    setLoadError(null)
    try {
      const { data } = await api.get(`/${resource}`, {
        params: { page, q: query || undefined, active: status || undefined },
      })
      setRows(data.data)
      setMeta({ current_page: data.current_page, last_page: data.last_page, total: data.total })
    } catch (err) {
      setLoadError(parseError(err).message)
    } finally {
      setLoading(false)
    }
  }, [resource, page, query, status])

  useEffect(() => { load() }, [load])

  useEffect(() => {
    const d = dialog.current
    if (!d) return
    if (editing && !d.open) d.showModal()
    if (!editing && d.open) d.close()
  }, [editing])

  function openForm(row) {
    setFields({})
    setFormError(null)
    setForm(row ? { ...EMPTY, ...Object.fromEntries(Object.entries(row).map(([k, v]) => [k, v ?? ''])) } : EMPTY)
    setEditing(row ?? {})
  }

  const set = (e) => {
    const { name, value, type, checked } = e.target
    setForm({ ...form, [name]: type === 'checkbox' ? checked : value })
  }

  async function submit(e) {
    e.preventDefault()
    setSaving(true)
    setFormError(null)
    setFields({})
    const payload = { ...form }
    if (payload.id) delete payload.id
    delete payload.created_at
    delete payload.updated_at
    try {
      if (editing.id) await api.put(`/${resource}/${editing.id}`, payload)
      else await api.post(`/${resource}`, payload)
      setEditing(null)
      await load()
    } catch (err) {
      const parsed = parseError(err)
      setFields(parsed.fields)
      setFormError(parsed.message)
    } finally {
      setSaving(false)
    }
  }

  async function remove(row) {
    if (!window.confirm(`Supprimer « ${row.name} » ? Cette action est définitive.`)) return
    try {
      await api.delete(`/${resource}/${row.id}`)
      // Si on vient de supprimer le dernier élément d'une page, on recule d'une page.
      if (rows.length === 1 && page > 1) setPage(page - 1)
      else await load()
    } catch (err) {
      setLoadError(parseError(err).message)
    }
  }

  return (
    <>
      <header className="page-head page-head-row">
        <div>
          <h1>{title}</h1>
          <p className="muted">{meta.total} {meta.total > 1 ? 'enregistrements' : 'enregistrement'}</p>
        </div>
        {canManage && <button className="btn btn-inline" onClick={() => openForm(null)}><AddIcon className="size-4" aria-hidden="true" /> Nouveau {singular}</button>}
      </header>

      <div className="toolbar">
        <input type="search" placeholder="Nom, téléphone ou ville" aria-label="Rechercher"
          value={search} onChange={(e) => setSearch(e.target.value)} />
        <select aria-label="Filtrer par statut" value={status}
          onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
          <option value="">Tous</option>
          <option value="1">Actifs</option>
          <option value="0">Inactifs</option>
        </select>
      </div>

      {loadError && <div role="alert" className="alert">{loadError}</div>}

      <ListCards loading={loading} isEmpty={rows.length === 0}
        empty={query || status ? 'Aucun résultat pour ces critères.' : `Aucun ${singular} pour le moment.`}>
        {rows.map((r) => (
          <ListCard key={r.id} title={r.name} badge={<span className={`pill ${r.is_active ? 'pill-on' : 'pill-off'}`}>{r.is_active ? 'Actif' : 'Inactif'}</span>}
            sub={[r.city, r.phone, r.email].filter(Boolean).join(' · ')}
            details={[[extra.label, extra.format(r[extra.name])]]}
            actions={canManage ? [{ label: 'Modifier', onClick: () => openForm(r) }, { label: 'Supprimer', danger: true, onClick: () => remove(r) }] : []} />
        ))}
      </ListCards>

      <div className="table-wrap max-lg:hidden" aria-busy={loading}>
        <table>
          <thead>
            <tr>
              <th>Nom</th><th>Contact</th><th>Ville</th><th>{extra.label}</th><th>Statut</th><th className="actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id}>
                <td><strong>{r.name}</strong></td>
                <td>
                  <div>{r.email || '—'}</div>
                  <div className="muted small">{r.phone || ''}</div>
                </td>
                <td>{r.city || '—'}</td>
                <td>{extra.format(r[extra.name])}</td>
                <td>
                  <span className={`pill ${r.is_active ? 'pill-on' : 'pill-off'}`}>
                    {r.is_active ? 'Actif' : 'Inactif'}
                  </span>
                </td>
                <td className="actions">
                  {canManage && <RowActions name={r.name} onEdit={() => openForm(r)} onDelete={() => remove(r)} />}
                </td>
              </tr>
            ))}
            {!loading && rows.length === 0 && (
              <tr><td colSpan="6" className="empty">
                {query || status ? 'Aucun résultat pour ces critères.' : `Aucun ${singular} pour le moment.`}
              </td></tr>
            )}
            {loading && rows.length === 0 && (
              <tr><td colSpan="6" className="empty">Chargement…</td></tr>
            )}
          </tbody>
        </table>
      </div>

      {meta.last_page > 1 && (
        <nav className="pager" aria-label="Pagination">
          <button disabled={page <= 1} onClick={() => setPage(page - 1)}>← Précédent</button>
          <span>Page {meta.current_page} / {meta.last_page}</span>
          <button disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>Suivant →</button>
        </nav>
      )}

      <dialog ref={dialog} className="modal" onClose={() => setEditing(null)}>
        {editing && (
          <form onSubmit={submit} noValidate>
            <h2>{editing.id ? `Modifier ${singular}` : `Nouveau ${singular}`}</h2>
            {formError && <div role="alert" className="alert">{formError}</div>}
            <Field label="Nom *" name="name" value={form.name} onChange={set} error={fields.name} required />
            <div className="row">
              <Field label="Email" name="email" type="email" value={form.email} onChange={set} error={fields.email} />
              <Field label="Téléphone" name="phone" value={form.phone} onChange={set} error={fields.phone} />
            </div>
            <Field label="Adresse" name="address" value={form.address} onChange={set} error={fields.address} />
            <div className="row">
              <Field label="Ville" name="city" value={form.city} onChange={set} error={fields.city} />
              <Field label="N° fiscal (NIF / ICE)" name="tax_id" value={form.tax_id} onChange={set} error={fields.tax_id} />
            </div>
            <Field label={extra.label} name={extra.name} type="number" min="0" step={extra.step}
              value={form[extra.name] ?? ''} onChange={set} error={fields[extra.name]} hint={extra.hint} />
            <div className="field">
              <label htmlFor="notes">Notes</label>
              <textarea id="notes" name="notes" rows="3" value={form.notes} onChange={set} />
              <p className="field-error">{fields.notes || ' '}</p>
            </div>
            <label className="check">
              <input type="checkbox" name="is_active" checked={Boolean(form.is_active)} onChange={set} /> Actif
            </label>
            <div className="modal-actions">
              <button type="button" className="btn btn-secondary" onClick={() => setEditing(null)}>Annuler</button>
              <button className="btn" disabled={saving}>{saving ? 'Enregistrement…' : 'Enregistrer'}</button>
            </div>
          </form>
        )}
      </dialog>
    </>
  )
}
