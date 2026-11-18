import { useEffect, useState } from 'react'
import { Link, useLocation, useParams } from 'react-router-dom'
import api, { parseError } from '../../api'
import { useAuth } from '../../auth'
import { fmtDate, fmtMoney, fmtQty } from '../../lib/format'
import { METHODS } from '../../lib/sales'

export default function Ticket() {
  const { id } = useParams()
  const { state } = useLocation()
  const { company, can } = useAuth()
  const [doc, setDoc] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    api.get(`/pos/sales/${id}`).then(({ data }) => setDoc(data)).catch((err) => setError(parseError(err).message))
  }, [id])

  if (error) return <div role="alert" className="alert">{error}</div>
  if (!doc) return <div className="splash" role="status">Chargement…</div>

  const change = Number(state?.change ?? 0)
  const named = doc.customer && !doc.customer.is_walk_in

  return (
    <>
      <div className="no-print ticket-actions">
        <Link to="/caisse" className="btn btn-inline btn-link">Nouvelle vente</Link>
        <button className="btn btn-secondary btn-inline" onClick={() => window.print()}>Imprimer le ticket</button>
        {can('sales.view') && <Link to={`/ventes/${doc.id}`} className="btn btn-secondary btn-inline btn-link">Voir la facture</Link>}
      </div>

      <article className="receipt">
        <h2 className="receipt-company">{company}</h2>
        <div className="receipt-center">TICKET DE CAISSE</div>
        <div className="receipt-center">{doc.number} · {fmtDate(doc.created_at)}</div>
        {doc.status === 'cancelled' && <div className="receipt-center receipt-void">*** VENTE ANNULÉE ***</div>}
        {named && <div className="receipt-center">Client : {doc.customer.name}</div>}
        <hr />
        {doc.lines.map((l) => (
          <div className="receipt-line" key={l.id}>
            <span>{fmtQty(l.quantity)} × {l.description}</span>
            <span>{fmtMoney(Number(l.line_total) * (1 + Number(l.tax_rate) / 100))}</span>
          </div>
        ))}
        <hr />
        <div className="receipt-line"><span>Total HT</span><span>{fmtMoney(doc.subtotal)}</span></div>
        <div className="receipt-line"><span>TVA</span><span>{fmtMoney(doc.tax_total)}</span></div>
        <div className="receipt-line receipt-total"><span>TOTAL TTC</span><span>{fmtMoney(doc.total)}</span></div>
        <hr />
        {doc.payments.map((p) => (
          <div className="receipt-line" key={p.id}><span>{METHODS[p.method]}</span><span>{fmtMoney(p.amount)}</span></div>
        ))}
        {change > 0 && <div className="receipt-line"><span>Monnaie rendue</span><span>{fmtMoney(change)}</span></div>}
        {Number(doc.balance) > 0 && doc.status !== 'cancelled' && (
          <div className="receipt-line receipt-total"><span>RESTE DÛ</span><span>{fmtMoney(doc.balance)}</span></div>
        )}
        <hr />
        <div className="receipt-center">Merci de votre visite !</div>
      </article>
    </>
  )
}
