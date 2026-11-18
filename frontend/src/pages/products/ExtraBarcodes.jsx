import { useEffect, useState } from 'react'
import api, { parseError } from '../../api'
import ScanButton from '../../components/ScanButton'
import { AddIcon, DeleteIcon, IconButton } from '../../components/ui'
import { fmtQty } from '../../lib/format'

/**
 * Codes-barres supplémentaires d'un produit (carton, autre fournisseur, ancien emballage).
 * Chaque code indique combien d'unités un scan représente : un carton de 12 = 12.
 */
export default function ExtraBarcodes({ product, unit = 'pièce' }) {
  const [codes, setCodes] = useState(product.barcodes ?? [])
  const [form, setForm] = useState({ barcode: '', label: '', quantity: '1' })
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    api.get(`/products/${product.id}/barcodes`).then(({ data }) => setCodes(data)).catch(() => {})
  }, [product.id])

  async function add() {
    setBusy(true); setError(null)
    try {
      const { data } = await api.post(`/products/${product.id}/barcodes`, { barcode: form.barcode.trim(), label: form.label.trim() || null, quantity: Number(form.quantity) })
      setCodes(data)
      setForm({ barcode: '', label: '', quantity: '1' })
    } catch (err) {
      const p = parseError(err)
      setError(p.fields.barcode || p.fields.quantity || p.message)
    } finally { setBusy(false) }
  }

  async function remove(c) {
    if (!window.confirm(`Supprimer le code ${c.barcode} ?`)) return
    try { const { data } = await api.delete(`/products/${product.id}/barcodes/${c.id}`); setCodes(data) } catch (err) { setError(parseError(err).message) }
  }

  return (
    <fieldset className="mb-4 rounded-xl border border-line p-4">
      <legend className="px-1 text-sm font-semibold">Codes supplémentaires</legend>
      <p className="mb-3 text-xs text-muted">Pour un carton ou un autre emballage : un scan de ce code compte autant d’unités que la quantité indiquée.</p>

      {codes.length > 0 && (
        <ul className="m-0 mb-3 grid list-none gap-1.5 p-0">
          {codes.map((c) => (
            <li key={c.id} className="flex items-center gap-3 rounded-lg bg-bg px-3 py-2 text-sm">
              <code className="text-xs">{c.barcode}</code>
              <span className="min-w-0 flex-1 truncate">{c.label || 'Sans libellé'}</span>
              <span className="pill pill-info">× {fmtQty(c.quantity)} {unit}</span>
              <IconButton icon={DeleteIcon} tone="danger" label={`Supprimer le code ${c.barcode}`} onClick={() => remove(c)} />
            </li>
          ))}
        </ul>
      )}

      <div className="grid gap-2 sm:grid-cols-[1fr_1fr_110px_auto]">
        <div className="flex gap-2">
          <input aria-label="Nouveau code" placeholder="Code" value={form.barcode} inputMode="numeric" onChange={(e) => setForm({ ...form, barcode: e.target.value })}
            className="min-w-0 flex-1 rounded-lg border border-line bg-surface px-3 py-2" />
          <ScanButton onScan={(c) => { setForm((f) => ({ ...f, barcode: c })); return `Code lu : ${c}` }} title="Lire le code supplémentaire" />
        </div>
        <input aria-label="Libellé" placeholder="Libellé (ex. Carton de 12)" value={form.label} maxLength={60} onChange={(e) => setForm({ ...form, label: e.target.value })}
          className="rounded-lg border border-line bg-surface px-3 py-2" />
        <input aria-label="Quantité par scan" type="number" min="0" step="any" placeholder="Quantité" value={form.quantity} onChange={(e) => setForm({ ...form, quantity: e.target.value })}
          className="rounded-lg border border-line bg-surface px-3 py-2 text-right" />
        <button type="button" className="btn btn-secondary btn-inline" onClick={add} disabled={busy || !form.barcode.trim() || Number(form.quantity) <= 0}>
          <AddIcon className="size-4" aria-hidden="true" /> Ajouter
        </button>
      </div>
      {error && <p role="alert" className="field-error mt-2">{error}</p>}
    </fieldset>
  )
}
