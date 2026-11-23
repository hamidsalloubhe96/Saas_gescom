import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import api, { parseError } from '../../api'
import { useAuth } from '../../auth'
import PaymentsPanel from '../../components/PaymentsPanel'
import StatusPill from '../../components/StatusPill'
import { fmtMoney, fmtQty } from '../../lib/format'
import { TYPE_LABEL, fmtDay } from '../../lib/sales'

export default function SalesView() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { company, can } = useAuth()
  const canManage = can('sales.manage')
  const [doc, setDoc] = useState(null)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    try {
      const { data } = await api.get(`/documents/${id}`)
      setDoc(data)
    } catch (err) {
      setError(err.response?.status === 404 ? 'Document introuvable.' : parseError(err).message)
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => { setLoading(true); setDoc(null); load() }, [load])

  /** Exécute un appel API d'action, remplace le document par la réponse et affiche l'erreur éventuelle. */
  async function act(call, { confirm } = {}) {
    if (confirm && !window.confirm(confirm)) return
    setBusy(true)
    setError(null)
    try {
      const { data } = await call()
      if (data?.id) setDoc(data)
      return data
    } catch (err) {
      setError(parseError(err).message)
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } finally {
      setBusy(false)
    }
  }

  const transition = (action, confirm) => act(() => api.post(`/documents/${id}/transition`, { action }), { confirm })

  async function convert() {
    const inv = await act(() => api.post(`/documents/${id}/convert`),
      { confirm: 'Créer une facture brouillon à partir de ce devis ?' })
    if (inv?.id) navigate(`/ventes/${inv.id}`)
  }

  async function remove() {
    if (!window.confirm(`Supprimer ce ${TYPE_LABEL[doc.type].toLowerCase()} ? Cette action est définitive.`)) return
    setBusy(true)
    try {
      await api.delete(`/documents/${id}`)
      navigate(`/ventes?type=${doc.type}`, { replace: true })
    } catch (err) {
      setError(parseError(err).message)
      setBusy(false)
    }
  }

  if (loading) return <div className="splash" role="status">Chargement…</div>
  if (!doc) return (
    <>
      <div role="alert" className="alert">{error}</div>
      <Link to="/ventes">← Retour aux documents</Link>
    </>
  )

  const isQuote = doc.type === 'quote'
  const s = doc.status
  const title = `${TYPE_LABEL[doc.type]} ${doc.number ?? '(brouillon)'}`
  const canPay = can('sales.pay') && !isQuote && ['issued', 'partial'].includes(s)

  return (
    <>
      <div className="no-print">
        <Link to={`/ventes?type=${doc.type}`} className="back">← {isQuote ? 'Devis' : 'Factures'}</Link>
        <header className="page-head page-head-row">
          <div>
            <h1>{TYPE_LABEL[doc.type]} <span className="whitespace-nowrap">{doc.number ?? '(brouillon)'}</span> <StatusPill doc={doc} /></h1>
            <p className="muted">
              {doc.customer?.name}
              {doc.source_quote && <> · issue du devis <Link to={`/ventes/${doc.source_quote.id}`}>{doc.source_quote.number}</Link></>}
              {doc.invoice && <> · facturé : <Link to={`/ventes/${doc.invoice.id}`}>{doc.invoice.number ?? 'brouillon'}</Link></>}
            </p>
          </div>
          <div className="head-actions wrap">
            {canManage && isQuote && s === 'draft' && <button className="btn btn-secondary btn-inline" disabled={busy} onClick={() => transition('send')}>Marquer envoyé</button>}
            {canManage && isQuote && ['draft', 'sent'].includes(s) && <>
              <button className="btn btn-secondary btn-inline" disabled={busy} onClick={() => transition('reject')}>Refusé</button>
              <button className="btn btn-inline" disabled={busy} onClick={() => transition('accept')}>Accepté</button>
            </>}
            {canManage && isQuote && s === 'accepted' && <button className="btn btn-inline" disabled={busy} onClick={convert}>Transformer en facture</button>}
            {canManage && isQuote && ['accepted', 'rejected'].includes(s) && <button className="btn btn-secondary btn-inline" disabled={busy} onClick={() => transition('reopen')}>Rouvrir</button>}
            {canManage && !isQuote && s === 'draft' && <button className="btn btn-inline" disabled={busy}
              onClick={() => transition('issue', 'Émettre la facture ? Un numéro définitif lui sera attribué, le stock des produits sera décrémenté et elle ne sera plus modifiable.')}>
              Émettre la facture</button>}
            {canManage && ((isQuote && ['draft', 'sent'].includes(s)) || (!isQuote && s === 'draft')) &&
              <Link to={`/ventes/${id}/modifier`} className="btn btn-secondary btn-inline btn-link">Modifier</Link>}
            {canManage && !isQuote && ['issued', 'partial'].includes(s) && <button className="btn btn-secondary btn-inline" disabled={busy}
              onClick={() => transition('cancel', 'Annuler cette facture ? Les quantités seront remises en stock. Le numéro ne sera pas réutilisé.')}>
              Annuler la facture</button>}
            <button className="btn btn-secondary btn-inline" onClick={() => window.print()}>Imprimer / PDF</button>
            {canManage && ((isQuote && s !== 'invoiced') || (!isQuote && s === 'draft')) &&
              <button className="btn btn-danger btn-inline" disabled={busy} onClick={remove}>Supprimer</button>}
          </div>
        </header>
        {error && <div role="alert" className="alert">{error}</div>}
      </div>

      <article className="sheet">
        <header className="sheet-head">
          <div>
            <div className="sheet-company print-only">{company}</div>
            <h2 className="sheet-title">{TYPE_LABEL[doc.type].toUpperCase()}</h2>
            <div className="sheet-number">{doc.number ?? 'Brouillon'}</div>
          </div>
          <dl className="sheet-meta">
            <div><dt>Date</dt><dd>{fmtDay(doc.issue_date)}</dd></div>
            <div><dt>{isQuote ? 'Valable jusqu\'au' : 'Échéance'}</dt><dd>{fmtDay(doc.due_date)}</dd></div>
          </dl>
        </header>

        <div className="sheet-party">
          <div className="muted small">{isQuote ? 'Devis pour' : 'Facturé à'}</div>
          <strong>{doc.customer?.name}</strong>
          {doc.customer?.address && <div>{doc.customer.address}</div>}
          {doc.customer?.city && <div>{doc.customer.city}</div>}
          {doc.customer?.tax_id && <div>N° fiscal : {doc.customer.tax_id}</div>}
        </div>

        <table className="lines view">
          <thead>
            <tr><th>Désignation</th><th className="num">Qté</th><th className="num">Prix unit.</th>
              <th className="num">Remise</th><th className="num">TVA</th><th className="num">Total HT</th></tr>
          </thead>
          <tbody>
            {doc.lines.map((l) => (
              <tr key={l.id}>
                <td>{l.description}</td>
                <td className="num">{fmtQty(l.quantity)} {l.unit}</td>
                <td className="num">{fmtMoney(l.unit_price)}</td>
                <td className="num">{Number(l.discount_rate) ? `${fmtQty(l.discount_rate)} %` : '—'}</td>
                <td className="num">{Number(l.tax_rate) ? `${fmtQty(l.tax_rate)} %` : '—'}</td>
                <td className="num">{fmtMoney(l.line_total)}</td>
              </tr>
            ))}
          </tbody>
        </table>

        <dl className="totals">
          <div><dt>Total HT</dt><dd>{fmtMoney(doc.subtotal)}</dd></div>
          <div><dt>TVA</dt><dd>{fmtMoney(doc.tax_total)}</dd></div>
          <div className="grand"><dt>Total TTC</dt><dd>{fmtMoney(doc.total)}</dd></div>
          {!isQuote && !['draft', 'cancelled'].includes(s) && <>
            <div><dt>Déjà payé</dt><dd>{fmtMoney(doc.amount_paid)}</dd></div>
            <div className="grand"><dt>Reste dû</dt><dd>{fmtMoney(doc.balance)}</dd></div>
          </>}
        </dl>

        {doc.notes && <p className="sheet-notes"><strong>Notes : </strong>{doc.notes}</p>}
      </article>

      {!isQuote && !['draft'].includes(s) && (
        <PaymentsPanel basePath="/documents" doc={doc} canPay={canPay} canRemove={can('sales.pay') && s !== 'cancelled'} busy={busy} act={act} />
      )}
    </>
  )
}
