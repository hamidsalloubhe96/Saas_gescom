import { useState } from 'react'
import api, { parseError } from '../../api'
import Field from '../../components/Field'
import { fmtQty } from '../../lib/format'

const TYPES = [
  { value: 'in', label: 'Entrée', qty: 'Quantité reçue', hint: 'Ajoute au stock' },
  { value: 'out', label: 'Sortie', qty: 'Quantité sortie', hint: 'Retire du stock' },
  { value: 'adjust', label: 'Inventaire', qty: 'Quantité comptée', hint: 'Remplace le stock par la quantité réellement comptée' },
]

export default function MovementForm({ product, onSaved, onCancel }) {
  const [type, setType] = useState('in')
  const [quantity, setQuantity] = useState('')
  const [reason, setReason] = useState('')
  const [fields, setFields] = useState({})
  const [error, setError] = useState(null)
  const [saving, setSaving] = useState(false)
  const current = TYPES.find((t) => t.value === type)

  async function submit(e) {
    e.preventDefault()
    setSaving(true)
    setError(null)
    setFields({})
    try {
      await api.post(`/products/${product.id}/movements`, { type, quantity, reason })
      onSaved()
    } catch (err) {
      const parsed = parseError(err)
      setFields(parsed.fields)
      setError(parsed.fields.quantity ? null : parsed.message)
    } finally {
      setSaving(false)
    }
  }

  return (
    <form onSubmit={submit} noValidate>
      <p className="muted" style={{ marginTop: 0 }}>
        <strong>{product.name}</strong> — stock actuel : <strong>{fmtQty(product.stock_quantity)} {product.unit}</strong>
      </p>
      {error && <div role="alert" className="alert">{error}</div>}
      <div className="segmented" role="radiogroup" aria-label="Type de mouvement">
        {TYPES.map((t) => (
          <button type="button" key={t.value} role="radio" aria-checked={type === t.value}
            className={type === t.value ? 'on' : ''} onClick={() => setType(t.value)}>
            {t.label}
          </button>
        ))}
      </div>
      <Field label={current.qty} name="quantity" type="number" min="0" step="any" autoFocus
        value={quantity} onChange={(e) => setQuantity(e.target.value)} error={fields.quantity} hint={current.hint} />
      <Field label="Motif" name="reason" value={reason} onChange={(e) => setReason(e.target.value)}
        error={fields.reason} hint="Ex. réception fournisseur, casse, inventaire annuel" />
      <div className="modal-actions">
        <button type="button" className="btn btn-secondary" onClick={onCancel}>Annuler</button>
        <button className="btn" disabled={saving}>{saving ? 'Enregistrement…' : 'Valider'}</button>
      </div>
    </form>
  )
}
