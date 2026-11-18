import { useState } from 'react'
import { parseError } from '../../api'
import { fmtMoney } from '../../lib/format'

const METHODS = { cash: 'Espèces', card: 'Carte', transfer: 'Virement', cheque: 'Chèque' }
const r2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100

/** Encaissement : un ou plusieurs modes de paiement, rendu de monnaie sur les espèces. */
export default function PaymentDialog({ total, walkIn, onCancel, onConfirm }) {
  const [rows, setRows] = useState([{ method: 'cash', amount: '' }])
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  const paid = r2(rows.reduce((s, r) => s + (Number(r.amount) || 0), 0))
  const remaining = r2(Math.max(0, total - paid))
  const hasCash = rows.some((r) => r.method === 'cash' && Number(r.amount) > 0)
  const change = hasCash ? r2(Math.max(0, paid - total)) : 0
  const overpaidNoCash = paid > total && !hasCash

  let blocker = null
  if (overpaidNoCash) blocker = 'Seules les espèces peuvent dépasser le montant dû.'
  else if (walkIn && remaining > 0) blocker = `Il manque ${fmtMoney(remaining)} (vente anonyme : paiement intégral requis).`

  const update = (i, patch) => setRows(rows.map((r, j) => (j === i ? { ...r, ...patch } : r)))

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await onConfirm(rows.filter((r) => Number(r.amount) > 0).map((r) => ({ method: r.method, amount: Number(r.amount) })))
    } catch (err) {
      setError(parseError(err).message)
      setBusy(false)
    }
  }

  return (
    <form onSubmit={submit} noValidate>
      <div className="pay-total"><span>Total à encaisser</span><strong>{fmtMoney(total)}</strong></div>
      {error && <div role="alert" className="alert">{error}</div>}

      {rows.map((r, i) => (
        <div className="pay-row" key={i}>
          <select aria-label={`Mode de paiement ${i + 1}`} value={r.method} onChange={(e) => update(i, { method: e.target.value })}>
            {Object.entries(METHODS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </select>
          <input type="number" min="0" step="0.01" inputMode="decimal" aria-label={`Montant ${i + 1}`} placeholder="Montant"
            autoFocus={i === 0} value={r.amount} onChange={(e) => update(i, { amount: e.target.value })} />
          <button type="button" className="btn btn-secondary btn-inline" title="Montant restant"
            onClick={() => update(i, { amount: String(r2((Number(r.amount) || 0) + remaining)) })}>Exact</button>
          {rows.length > 1 && (
            <button type="button" className="link-btn danger" aria-label={`Retirer le mode ${i + 1}`}
              onClick={() => setRows(rows.filter((_, j) => j !== i))}>✕</button>
          )}
        </div>
      ))}
      <button type="button" className="link-btn" onClick={() => setRows([...rows, { method: 'card', amount: '' }])}>
        + Ajouter un mode de paiement
      </button>

      <dl className="totals">
        <div><dt>Reçu</dt><dd>{fmtMoney(paid)}</dd></div>
        <div><dt>Reste à payer</dt><dd>{fmtMoney(remaining)}</dd></div>
        <div className={change > 0 ? 'grand change' : ''}><dt>Monnaie à rendre</dt><dd>{fmtMoney(change)}</dd></div>
      </dl>

      {blocker && <p className="field-error">{blocker}</p>}
      {!walkIn && remaining > 0 && !blocker && (
        <p className="field-hint">Client identifié : le solde de {fmtMoney(remaining)} restera dû sur sa facture (vente à crédit).</p>
      )}

      <div className="modal-actions">
        <button type="button" className="btn btn-secondary" onClick={onCancel}>Retour</button>
        <button className="btn" disabled={busy || Boolean(blocker)}>{busy ? 'Validation…' : 'Valider la vente'}</button>
      </div>
    </form>
  )
}
