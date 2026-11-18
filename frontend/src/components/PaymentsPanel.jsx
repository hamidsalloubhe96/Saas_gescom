import { useEffect, useState } from 'react'
import api from '../api'
import { fmtMoney } from '../lib/format'
import { METHODS, fmtDay, today } from '../lib/sales'
import Field from './Field'

/**
 * Liste des paiements d'un document + formulaire d'ajout.
 * `basePath` : '/documents' (encaissements clients) ou '/purchases' (règlements fournisseurs).
 */
export default function PaymentsPanel({ basePath, doc, canPay, canRemove, busy, act, title = 'Paiements' }) {
  const [form, setForm] = useState({ amount: '', method: 'cash', paid_at: today(), reference: '' })

  // Pré-remplit le montant avec le solde restant.
  useEffect(() => { setForm((f) => ({ ...f, amount: doc.balance })) }, [doc.balance])

  function add(e) {
    e.preventDefault()
    act(() => api.post(`${basePath}/${doc.id}/payments`, form))
  }

  return (
    <section className="panel no-print">
      <h2>{title}</h2>
      <table className="lines view">
        <thead><tr><th>Date</th><th>Mode</th><th>Référence</th><th className="num">Montant</th><th /></tr></thead>
        <tbody>
          {doc.payments.map((p) => (
            <tr key={p.id}>
              <td>{fmtDay(p.paid_at)}</td><td>{METHODS[p.method]}</td><td>{p.reference || '—'}</td>
              <td className="num">{fmtMoney(p.amount)}</td>
              <td className="actions">
                {canRemove && (
                  <button className="link-btn danger" disabled={busy}
                    onClick={() => act(() => api.delete(`${basePath}/${doc.id}/payments/${p.id}`), { confirm: 'Supprimer ce paiement ?' })}>
                    Supprimer</button>
                )}
              </td>
            </tr>
          ))}
          {doc.payments.length === 0 && <tr><td colSpan="5" className="empty">Aucun paiement enregistré.</td></tr>}
        </tbody>
      </table>

      {canPay && (
        <form onSubmit={add} noValidate className="pay-form">
          <Field label="Montant" name="amount" type="number" min="0" step="0.01" value={form.amount}
            onChange={(e) => setForm({ ...form, amount: e.target.value })} />
          <div className="field">
            <label htmlFor="method">Mode</label>
            <select id="method" value={form.method} onChange={(e) => setForm({ ...form, method: e.target.value })}>
              {Object.entries(METHODS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
            </select>
            <p className="field-hint">&nbsp;</p>
          </div>
          <Field label="Date" name="paid_at" type="date" value={form.paid_at}
            onChange={(e) => setForm({ ...form, paid_at: e.target.value })} />
          <Field label="Référence" name="reference" value={form.reference}
            onChange={(e) => setForm({ ...form, reference: e.target.value })} />
          <button className="btn btn-inline pay-btn" disabled={busy}>Enregistrer</button>
        </form>
      )}
    </section>
  )
}
