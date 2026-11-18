import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import api, { parseError } from '../api'
import { fmtMoney } from '../lib/format'

/** Reçu imprimable d'un paiement d'abonnement validé. */
export default function Receipt() {
  const { id } = useParams()
  const [r, setR] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    api.get(`/subscription/payments/${id}/receipt`).then(({ data }) => setR(data)).catch((e) => setError(parseError(e).message))
  }, [id])

  if (error) return <div role="alert" className="alert">{error}</div>
  if (!r) return <div className="splash" role="status">Chargement…</div>

  return (
    <>
      <header className="page-head no-print">
        <h1>Reçu {r.number}</h1>
        <div><Link to="/abonnement" className="btn btn-secondary btn-inline">← Abonnement</Link> <button className="btn btn-inline" onClick={() => window.print()}>Imprimer</button></div>
      </header>
      <section className="panel" style={{ maxWidth: 640 }}>
        <h2>Reçu de paiement · {r.issuer}</h2>
        <table>
          <tbody>
            <tr><th scope="row">N°</th><td>{r.number}</td></tr>
            <tr><th scope="row">Entreprise</th><td>{r.company}</td></tr>
            <tr><th scope="row">Forfait</th><td>{r.plan ?? '—'} · {r.months} période(s)</td></tr>
            <tr><th scope="row">Mode</th><td>{r.method}{r.reference ? ` · réf. ${r.reference}` : ''}</td></tr>
            <tr><th scope="row">Payé le</th><td>{r.paid_at ? new Date(r.paid_at).toLocaleDateString('fr-FR') : '—'}</td></tr>
            <tr><th scope="row">Validé le</th><td>{new Date(r.validated_at).toLocaleDateString('fr-FR')}</td></tr>
            <tr><th scope="row">Montant reçu</th><td><strong>{fmtMoney(r.amount)} {r.currency}</strong></td></tr>
          </tbody>
        </table>
      </section>
    </>
  )
}
