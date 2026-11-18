import { useCallback, useEffect, useState } from 'react'
import { AddIcon, IconButton, RowActions, ToggleIcon } from '../components/ui'
import api, { parseError, storage } from '../api'
import Field from '../components/Field'
import Modal from '../components/Modal'
import { fmtMoney } from '../lib/format'

const EMPTY = { name: '', code: '', region: '', city: '', address: '', phone: '' }

/** Points de vente : vue comparative (ventes, stock, caisse) et gestion (siège uniquement). */
export default function Branches() {
  const [data, setData] = useState(null)
  const [error, setError] = useState(null)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(EMPTY)
  const [fields, setFields] = useState({})
  const [formError, setFormError] = useState(null)
  const [saving, setSaving] = useState(false)

  const load = useCallback(() => api.get('/branches/overview').then(({ data: d }) => setData(d)).catch((e) => setError(parseError(e).message)), [])
  useEffect(() => { load() }, [load])

  function open(b) {
    setFields({}); setFormError(null)
    setForm(b ? { name: b.name, code: b.code ?? '', region: b.region ?? '', city: b.city ?? '', address: '', phone: '' } : EMPTY)
    setEditing(b ?? {})
    if (b) api.get('/branches').then(({ data: all }) => {
      const full = all.find((x) => x.id === b.id)
      if (full) setForm({ name: full.name, code: full.code ?? '', region: full.region ?? '', city: full.city ?? '', address: full.address ?? '', phone: full.phone ?? '' })
    })
  }

  async function submit(e) {
    e.preventDefault()
    setSaving(true); setFormError(null); setFields({})
    try {
      if (editing.id) await api.put(`/branches/${editing.id}`, form)
      else await api.post('/branches', form)
      setEditing(null)
      await load()
    } catch (err) {
      const p = parseError(err)
      setFields(p.fields); setFormError(p.message)
    } finally { setSaving(false) }
  }

  async function toggle(b) {
    setError(null)
    try { await api.put(`/branches/${b.id}`, { name: b.name, is_active: !b.is_active }); await load() } catch (err) { setError(parseError(err).message) }
  }

  function view(b) {
    // Affiche l'application pour ce point de vente (tableau de bord, stock, ventes).
    if (b.is_main) storage.remove('branch'); else storage.set('branch', String(b.id))
    window.location.assign('/')
  }

  if (!data) return error ? <div role="alert" className="alert">{error}</div> : <div className="splash" role="status">Chargement…</div>
  const t = data.totals

  return (
    <>
      <header className="page-head page-head-row">
        <div><h1>Points de vente</h1><p className="muted">Ventes, stock et caisse de chaque point de vente. Le stock des points de vente vient du siège par transferts.</p></div>
        <button className="btn btn-inline" onClick={() => open(null)}><AddIcon className="size-4" aria-hidden="true" /> Nouveau point de vente</button>
      </header>
      {error && <div role="alert" className="alert">{error}</div>}

      <div className="cards">
        <div className="card"><p className="muted small">Ventes aujourd'hui</p><strong>{fmtMoney(t.sales_today)}</strong></div>
        <div className="card"><p className="muted small">Ventes du mois</p><strong>{fmtMoney(t.sales_month)}</strong></div>
        <div className="card"><p className="muted small">Valeur du stock</p><strong>{fmtMoney(t.stock_value)}</strong></div>
        <div className="card"><p className="muted small">Produits en stock bas</p><strong>{t.low_stock}</strong></div>
      </div>

      <div className="table-wrap">
        <table>
          <thead><tr><th>Point de vente</th><th className="num">Ventes du jour</th><th className="num">Ventes du mois</th><th className="num">Valeur du stock</th><th className="num">Stock bas</th><th>Caisse</th><th>Transferts</th><th /></tr></thead>
          <tbody>
            {data.branches.map((b) => (
              <tr key={b.id} className={b.is_active ? '' : 'muted'}>
                <td><strong>{b.name}</strong> {b.is_main && <span className="pill pill-info">Siège</span>} {!b.is_active && <span className="pill">Inactif</span>}
                  <div className="muted small">{[b.code, b.region || b.city].filter(Boolean).join(' · ')}</div></td>
                <td className="num">{fmtMoney(b.sales_today)}</td>
                <td className="num">{fmtMoney(b.sales_month)}<div className="muted small">{b.invoices_month} facture{b.invoices_month > 1 ? 's' : ''}</div></td>
                <td className="num">{fmtMoney(b.stock_value)}</td>
                <td className="num">{b.low_stock > 0 ? <span className="pill pill-warn">{b.low_stock}</span> : 0}</td>
                <td>{b.register_open ? <span className="pill pill-on">Ouverte</span> : <span className="muted">Fermée</span>}</td>
                <td>{b.pending_transfers > 0 ? `${b.pending_transfers} en cours` : '—'}</td>
                <td className="row-actions">
                  <RowActions name={b.name} onView={() => view(b)} onEdit={() => open(b)}
                    extra={!b.is_main && <IconButton icon={ToggleIcon} tone={b.is_active ? 'danger' : 'success'} label={`${b.is_active ? 'Désactiver' : 'Réactiver'} : ${b.name}`} onClick={() => toggle(b)} />} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <Modal open={Boolean(editing)} onClose={() => setEditing(null)} title={editing?.id ? 'Modifier le point de vente' : 'Nouveau point de vente'}>
        {editing && (
          <form onSubmit={submit} noValidate>
            {formError && <div role="alert" className="alert">{formError}</div>}
            <Field label="Nom *" name="name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} error={fields.name} />
            <Field label="Code" name="code" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} error={fields.code} hint="Lettres et chiffres ; généré à partir du nom si vide" />
            <div className="row">
              <Field label="Région" name="region" value={form.region} onChange={(e) => setForm({ ...form, region: e.target.value })} error={fields.region} />
              <Field label="Ville" name="city" value={form.city} onChange={(e) => setForm({ ...form, city: e.target.value })} error={fields.city} />
            </div>
            <Field label="Adresse" name="address" value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} error={fields.address} />
            <Field label="Téléphone" name="phone" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} error={fields.phone} />
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
