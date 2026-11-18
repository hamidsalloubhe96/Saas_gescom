import { useState } from 'react'
import api, { parseError } from '../../api'
import { fmtMoney } from '../../lib/format'
import { METHODS } from '../../lib/sales'

const r2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100

export default function CloseRegister({ session, onCancel, onClosed }) {
  const s = session.summary
  const [counted, setCounted] = useState('')
  const [notes, setNotes] = useState('')
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)
  const [result, setResult] = useState(null)

  const diff = counted === '' ? null : r2(Number(counted) - s.expected_cash)

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const { data } = await api.post('/pos/session/close', { counted_cash: counted, notes })
      setResult(data)
    } catch (err) {
      setError(parseError(err).message)
      setBusy(false)
    }
  }

  if (result) {
    const d = Number(result.difference)
    return (
      <>
        <div className={`alert ${d === 0 ? 'alert-ok' : ''}`} role="status">
          Caisse clôturée. {d === 0 ? 'Aucun écart.' : `Écart de caisse : ${d > 0 ? '+' : ''}${fmtMoney(d)}.`}
        </div>
        <div className="modal-actions"><button className="btn" onClick={onClosed}>Fermer</button></div>
      </>
    )
  }

  return (
    <form onSubmit={submit} noValidate>
      {error && <div role="alert" className="alert">{error}</div>}
      <dl className="sum-list">
        <div><dt>Fond de caisse</dt><dd>{fmtMoney(session.opening_amount)}</dd></div>
        <div><dt>Ventes ({s.sales_count})</dt><dd>{fmtMoney(s.total_sales)}</dd></div>
        {Object.entries(s.payments).map(([m, v]) => (
          <div key={m}><dt>Encaissé — {METHODS[m] ?? m}</dt><dd>{fmtMoney(v)}</dd></div>
        ))}
        <div className="grand"><dt>Espèces attendues en caisse</dt><dd>{fmtMoney(s.expected_cash)}</dd></div>
      </dl>

      <div className="field">
        <label htmlFor="counted">Espèces comptées *</label>
        <input id="counted" type="number" min="0" step="0.01" inputMode="decimal" autoFocus value={counted}
          onChange={(e) => setCounted(e.target.value)} />
        <p className={diff ? 'field-error' : 'field-hint'}>
          {diff === null ? 'Comptez les espèces présentes dans le tiroir-caisse.'
            : diff === 0 ? 'Aucun écart.' : `Écart : ${diff > 0 ? '+' : ''}${fmtMoney(diff)}`}
        </p>
      </div>
      <div className="field">
        <label htmlFor="notes">Remarque</label>
        <textarea id="notes" rows="2" value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={1000} />
      </div>

      <div className="modal-actions">
        <button type="button" className="btn btn-secondary" onClick={onCancel}>Annuler</button>
        <button className="btn" disabled={busy || counted === ''}>{busy ? 'Clôture…' : 'Clôturer la caisse'}</button>
      </div>
    </form>
  )
}
