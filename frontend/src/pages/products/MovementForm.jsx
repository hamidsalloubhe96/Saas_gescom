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
  const [batch, setBatch] = useState({ batch_number: '', expires_at: '' })
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
      await api.post(`/products/${product.id}/movements`, { type, quantity, reason, ...(type === 'in' && product.track_batches ? batch : {}) })
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
      {type === 'in' && product.track_batches && (
        <div className="row">
          <Field label="N° de lot" name="batch_number" value={batch.batch_number} onChange={(e) => setBatch({ ...batch, batch_number: e.target.value })} error={fields.batch_number} />
          <Field label="Date de péremption" name="expires_at" type="date" value={batch.expires_at} onChange={(e) => setBatch({ ...batch, expires_at: e.target.value })} error={fields.expires_at} />
        </div>
      )}
      {type !== 'in' && product.track_batches && <p className="field-hint">Les lots qui périment en premier sortent en premier.</p>}
      <Field label="Motif" name="reason" value={reason} onChange={(e) => setReason(e.target.value)}
        error={fields.reason} hint="Ex. réception fournisseur, casse, inventaire annuel" />
      <div className="modal-actions">
        <button type="button" className="btn btn-secondary" onClick={onCancel}>Annuler</button>
        <button className="btn" disabled={saving}>{saving ? 'Enregistrement…' : 'Valider'}</button>
      </div>
    </form>
  )
}
