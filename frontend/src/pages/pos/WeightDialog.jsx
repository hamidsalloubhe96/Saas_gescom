import { useEffect, useRef, useState } from 'react'
import { fmtMoney0, fmtQty } from '../../lib/format'
import { connectScale, scaleSupported } from '../../lib/scale'

const QUICK = [0.25, 0.5, 1, 2]

/** Saisie du poids (ou du volume) d'un produit vendu au poids : le montant est arrondi à l'unité. */
export default function WeightDialog({ product, initial, onConfirm, onCancel }) {
  const [value, setValue] = useState(initial ? String(initial) : '')
  const [error, setError] = useState(null)
  const [scaleOn, setScaleOn] = useState(false)
  const stopScale = useRef(null)

  // La liaison avec la balance se referme avec la fenêtre.
  useEffect(() => () => { stopScale.current?.() }, [])

  async function toggleScale() {
    if (stopScale.current) {
      await stopScale.current()
      stopScale.current = null
      return setScaleOn(false)
    }
    try {
      stopScale.current = await connectScale((w) => { setValue(String(w)); setError(null) })
      setScaleOn(true)
    } catch (err) {
      if (err?.name !== 'NotFoundError') setError('Impossible de lire la balance. Vérifiez le câble et le port choisi.')
    }
  }
  const stock = Number(product.stock_quantity)
  const min = Number(product.min_sale_quantity) || 0
  const weight = Number(value.replace(',', '.')) || 0
  const amount = Math.round(weight * Number(product.sale_price))

  function submit(e) {
    e.preventDefault()
    if (weight <= 0) return setError('Saisissez le poids.')
    if (weight < min) return setError(`Poids minimum : ${fmtQty(min)} ${product.unit}.`)
    if (weight > stock) return setError(`Stock disponible : ${fmtQty(stock)} ${product.unit}.`)
    onConfirm(Math.round(weight * 1000) / 1000)
  }

  return (
    <form onSubmit={submit} noValidate>
      <p className="muted" style={{ marginTop: 0 }}>
        <strong>{product.name}</strong> — {fmtMoney0(product.sale_price)} le {product.unit}
      </p>
      {error && <div role="alert" className="alert">{error}</div>}
      <div className="field">
        <label htmlFor="weight">Poids ({product.unit})</label>
        <input id="weight" type="number" min="0" step="0.001" inputMode="decimal" autoFocus className="text-2xl font-semibold"
          value={value} onChange={(e) => { setValue(e.target.value); setError(null) }} />
      </div>
      <div className="mb-4 flex flex-wrap gap-2">
        {scaleSupported() && (
          <button type="button" className={`btn btn-inline ${scaleOn ? '' : 'btn-secondary'}`} onClick={toggleScale} aria-pressed={scaleOn}>
            {scaleOn ? 'Balance connectée : arrêter' : 'Lire la balance'}
          </button>
        )}
        {QUICK.map((q) => (
          <button key={q} type="button" className="btn btn-secondary btn-inline" onClick={() => { setValue(String(q)); setError(null) }}>
            {q < 1 ? `${q * 1000} g` : `${q} ${product.unit}`}
          </button>
        ))}
      </div>
      <div className="mb-4 flex items-baseline justify-between">
        <span className="muted">Montant</span>
        <strong className="text-xl">{fmtMoney0(amount)}</strong>
      </div>
      <div className="modal-actions">
        <button type="button" className="btn btn-secondary" onClick={onCancel}>Annuler</button>
        <button className="btn">{initial ? 'Modifier le poids' : 'Ajouter au panier'}</button>
      </div>
    </form>
  )
}
