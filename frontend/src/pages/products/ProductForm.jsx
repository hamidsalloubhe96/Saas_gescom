import { useState } from 'react'
import api, { parseError } from '../../api'
import Field from '../../components/Field'
import ScanButton from '../../components/ScanButton'
import ExtraBarcodes from './ExtraBarcodes'
import { GenerateIcon } from '../../components/ui'
import { barcodeWarning } from '../../lib/barcode'

const EMPTY = {
  name: '', sku: '', barcode: '', category_id: '', unit: 'pièce', purchase_price: '', sale_price: '',
  tax_rate: '', min_stock: '', initial_stock: '', notes: '', is_active: true,
}

export default function ProductForm({ product, categories, onSaved, onCancel, defaults }) {
  const isEdit = Boolean(product?.id)
  const [form, setForm] = useState(() =>
    isEdit
      ? Object.fromEntries(Object.keys(EMPTY).map((k) => [k, product[k] ?? EMPTY[k]]))
      : { ...EMPTY, ...defaults },
  )
  const [fields, setFields] = useState({})
  const [error, setError] = useState(null)
  const [saving, setSaving] = useState(false)
  const [generating, setGenerating] = useState(false)

  async function generateCode() {
    setGenerating(true)
    try {
      const { data } = await api.post('/products/barcode/generate')
      setForm((f) => ({ ...f, barcode: data.barcode }))
    } catch (err) {
      setError(parseError(err).message)
    } finally {
      setGenerating(false)
    }
  }

  const set = (e) => {
    const { name, value, type, checked } = e.target
    setForm({ ...form, [name]: type === 'checkbox' ? checked : value })
  }

  async function submit(e) {
    e.preventDefault()
    setSaving(true)
    setError(null)
    setFields({})
    const payload = { ...form }
    if (isEdit) delete payload.initial_stock
    try {
      if (isEdit) await api.put(`/products/${product.id}`, payload)
      else await api.post('/products', payload)
      onSaved()
    } catch (err) {
      const parsed = parseError(err)
      setFields(parsed.fields)
      setError(parsed.message)
    } finally {
      setSaving(false)
    }
  }

  return (
    <form onSubmit={submit} noValidate>
      {error && <div role="alert" className="alert">{error}</div>}
      <Field label="Désignation *" name="name" value={form.name} onChange={set} error={fields.name} required />
      <div className="row">
        <Field label="Référence (SKU)" name="sku" value={form.sku} onChange={set} error={fields.sku}
          hint="Laissez vide pour une génération automatique" />
        <div className="field">
          <label htmlFor="barcode">Code-barres</label>
          <div className="flex gap-2">
            <input id="barcode" name="barcode" value={form.barcode} onChange={set} aria-invalid={Boolean(fields.barcode)} inputMode="numeric" className="!min-w-0 flex-1" />
            <ScanButton onScan={(c) => { setForm((f) => ({ ...f, barcode: c })); return `Code lu : ${c}` }} title="Lire le code-barres du produit" />
            <button type="button" className="btn btn-secondary btn-inline" onClick={generateCode} disabled={generating} title="Créer un code interne (préfixe 200)">
              <GenerateIcon className="size-4" aria-hidden="true" /> <span className="hidden sm:inline">Générer</span>
            </button>
          </div>
          <p className={fields.barcode || barcodeWarning(form.barcode) ? 'field-error' : 'field-hint'}>
            {fields.barcode || barcodeWarning(form.barcode) || 'Scannez, saisissez ou générez un code interne.'}
          </p>
        </div>
      </div>
      {isEdit
        ? <ExtraBarcodes product={product} unit={form.unit || 'pièce'} />
        : <p className="mb-4 text-xs text-muted">Enregistrez d’abord le produit pour lui ajouter d’autres codes (carton, autre fournisseur).</p>}
      <div className="row">
        <div className="field">
          <label htmlFor="category_id">Catégorie</label>
          <select id="category_id" name="category_id" value={form.category_id ?? ''} onChange={set}>
            <option value="">— Aucune —</option>
            {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
          <p className="field-error">{fields.category_id || ' '}</p>
        </div>
        <Field label="Unité" name="unit" value={form.unit} onChange={set} error={fields.unit}
          hint="pièce, kg, litre, carton…" />
      </div>
      <div className="row">
        <Field label="Prix d'achat" name="purchase_price" type="number" min="0" step="0.01"
          value={form.purchase_price} onChange={set} error={fields.purchase_price} />
        <Field label="Prix de vente" name="sale_price" type="number" min="0" step="0.01"
          value={form.sale_price} onChange={set} error={fields.sale_price} />
        <Field label="TVA (%)" name="tax_rate" type="number" min="0" max="100" step="0.01"
          value={form.tax_rate} onChange={set} error={fields.tax_rate} />
      </div>
      <div className="row">
        {!isEdit && (
          <Field label="Stock initial" name="initial_stock" type="number" min="0" step="any"
            value={form.initial_stock} onChange={set} error={fields.initial_stock} />
        )}
        <Field label="Seuil d'alerte" name="min_stock" type="number" min="0" step="any"
          value={form.min_stock} onChange={set} error={fields.min_stock}
          hint="Alerte « stock bas » (0 = désactivée)" />
      </div>
      <div className="field">
        <label htmlFor="notes">Notes</label>
        <textarea id="notes" name="notes" rows="2" value={form.notes ?? ''} onChange={set} />
        <p className="field-error">{fields.notes || ' '}</p>
      </div>
      <label className="check">
        <input type="checkbox" name="is_active" checked={Boolean(form.is_active)} onChange={set} /> Produit actif
      </label>
      <div className="modal-actions">
        <button type="button" className="btn btn-secondary" onClick={onCancel}>Annuler</button>
        <button className="btn" disabled={saving}>{saving ? 'Enregistrement…' : 'Enregistrer'}</button>
      </div>
    </form>
  )
}
