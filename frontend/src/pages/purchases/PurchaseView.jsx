import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import api, { parseError } from '../../api'
import { useAuth } from '../../auth'
import PaymentsPanel from '../../components/PaymentsPanel'
import PurchasePill from '../../components/PurchasePill'
import ScanButton from '../../components/ScanButton'
import { fmtMoney, fmtQty } from '../../lib/format'
import { paymentState, remaining } from '../../lib/purchases'
import { fmtDay } from '../../lib/sales'

export default function PurchaseView() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { company, can } = useAuth()
  const canManage = can('purchases.manage')
  const [po, setPo] = useState(null)
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    try {
      const { data } = await api.get(`/purchases/${id}`)
      setPo(data)
    } catch (err) {
      setError(err.response?.status === 404 ? 'Commande introuvable.' : parseError(err).message)
    } finally {
      setLoading(false)
    }
  }, [id])

  useEffect(() => { setLoading(true); setPo(null); load() }, [load])

  /** Exécute une action API, remplace la commande par la réponse et affiche l'erreur éventuelle. */
  async function act(call, { confirm } = {}) {
    if (confirm && !window.confirm(confirm)) return
    setBusy(true)
    setError(null)
    try {
      const { data } = await call()
      if (data?.id) setPo(data)
      return data
    } catch (err) {
      setError(parseError(err).message)
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } finally {
      setBusy(false)
    }
  }

  const transition = (action, confirm) => act(() => api.post(`/purchases/${id}/transition`, { action }), { confirm })

  async function remove() {
    if (!window.confirm('Supprimer cette commande ? Cette action est définitive.')) return
    setBusy(true)
    try {
      await api.delete(`/purchases/${id}`)
      navigate('/achats', { replace: true })
    } catch (err) {
      setError(parseError(err).message)
      setBusy(false)
    }
  }

  if (loading) return <div className="splash" role="status">Chargement…</div>
  if (!po) return (
    <>
      <div role="alert" className="alert">{error}</div>
      <Link to="/achats">← Retour aux achats</Link>
    </>
  )

  const s = po.status
  const pay = paymentState(po)
  const canReceive = can('purchases.receive') && ['ordered', 'partial'].includes(s)
  const showPayments = ['ordered', 'partial', 'received'].includes(s)

  return (
    <>
      <div className="no-print">
        <Link to="/achats" className="back">← Achats</Link>
        <header className="page-head page-head-row">
          <div>
            <h1>Commande {po.number} <PurchasePill po={po} /></h1>
            <p className="muted">
              {po.supplier?.name}
              {pay && <> · <span className={`pill pill-${pay[1]}`}>{pay[0]}</span></>}
            </p>
          </div>
          <div className="head-actions wrap">
            {canManage && s === 'draft' && <>
              <button className="btn btn-inline" disabled={busy}
                onClick={() => transition('order', 'Passer la commande au fournisseur ? Elle ne sera plus modifiable.')}>
                Passer la commande</button>
              <Link to={`/achats/${id}/modifier`} className="btn btn-secondary btn-inline btn-link">Modifier</Link>
            </>}
            {canManage && s === 'ordered' && <button className="btn btn-secondary btn-inline" disabled={busy}
              onClick={() => transition('cancel', 'Annuler cette commande ? Aucune marchandise n\'a été reçue.')}>
              Annuler la commande</button>}
            {canManage && s === 'partial' && <button className="btn btn-secondary btn-inline" disabled={busy}
              onClick={() => transition('close', 'Clôturer la commande ? Le reliquat non livré ne sera plus attendu.')}>
              Clôturer (reliquat abandonné)</button>}
            <button className="btn btn-secondary btn-inline" onClick={() => window.print()}>Imprimer / PDF</button>
            {canManage && s === 'draft' && <button className="btn btn-danger btn-inline" disabled={busy} onClick={remove}>Supprimer</button>}
          </div>
        </header>
        {error && <div role="alert" className="alert">{error}</div>}
      </div>

      <article className="sheet">
        <header className="sheet-head">
          <div>
            <div className="sheet-company print-only">{company}</div>
            <h2 className="sheet-title">BON DE COMMANDE</h2>
            <div className="sheet-number">{po.number}</div>
          </div>
          <dl className="sheet-meta">
            <div><dt>Date</dt><dd>{fmtDay(po.order_date)}</dd></div>
            <div><dt>Livraison prévue</dt><dd>{fmtDay(po.expected_date)}</dd></div>
          </dl>
        </header>

        <div className="sheet-party">
          <div className="muted small">Fournisseur</div>
          <strong>{po.supplier?.name}</strong>
          {po.supplier?.address && <div>{po.supplier.address}</div>}
          {po.supplier?.city && <div>{po.supplier.city}</div>}
          {po.supplier?.tax_id && <div>N° fiscal : {po.supplier.tax_id}</div>}
        </div>

        <table className="lines view">
          <thead>
            <tr><th>Désignation</th><th className="num">Commandé</th><th className="num no-print">Reçu</th>
              <th className="num">Prix unit.</th><th className="num">TVA</th><th className="num">Total HT</th></tr>
          </thead>
          <tbody>
            {po.lines.map((l) => (
              <tr key={l.id}>
                <td>{l.description}{!l.product_id && <span className="muted small"> (hors stock)</span>}</td>
                <td className="num">{fmtQty(l.quantity)} {l.unit}</td>
                <td className="num no-print">
                  {['draft', 'cancelled'].includes(s) ? '—' : (
                    <span className={remaining(l) <= 0 ? 'qty-pos' : ''}>{fmtQty(l.quantity_received)}</span>
                  )}
                </td>
                <td className="num">{fmtMoney(l.unit_price)}</td>
                <td className="num">{Number(l.tax_rate) ? `${fmtQty(l.tax_rate)} %` : '—'}</td>
                <td className="num">{fmtMoney(l.line_total)}</td>
              </tr>
            ))}
          </tbody>
        </table>

        <dl className="totals">
          <div><dt>Total HT</dt><dd>{fmtMoney(po.subtotal)}</dd></div>
          <div><dt>TVA</dt><dd>{fmtMoney(po.tax_total)}</dd></div>
          <div className="grand"><dt>Total TTC</dt><dd>{fmtMoney(po.total)}</dd></div>
          {showPayments && <>
            <div className="no-print"><dt>Déjà réglé</dt><dd>{fmtMoney(po.amount_paid)}</dd></div>
            <div className="grand no-print"><dt>Reste à payer</dt><dd>{fmtMoney(po.balance)}</dd></div>
          </>}
        </dl>

        {po.notes && <p className="sheet-notes"><strong>Notes : </strong>{po.notes}</p>}
      </article>

      {canReceive && <Reception po={po} busy={busy} act={act} />}

      {showPayments && (
        <PaymentsPanel basePath="/purchases" title="Règlements fournisseur" doc={po}
          canPay={can('purchases.pay') && Number(po.balance) > 0} canRemove={can('purchases.pay')} busy={busy} act={act} />
      )}
    </>
  )
}

function Reception({ po, busy, act }) {
  const open = po.lines.filter((l) => remaining(l) > 0)
  // Par défaut, on propose de réceptionner tout le reste à recevoir.
  const [qty, setQty] = useState(() => Object.fromEntries(open.map((l) => [l.id, String(remaining(l))])))
  const scanning = useRef(false) // vrai dès le premier scan : on compte alors à partir de zéro

  // Après chaque réception, on recale les quantités proposées sur le nouveau reste.
  useEffect(() => {
    setQty(Object.fromEntries(po.lines.filter((l) => remaining(l) > 0).map((l) => [l.id, String(remaining(l))])))
    scanning.current = false
  }, [po.lines])

  if (open.length === 0) return null

  /** Code lu : la quantité reçue de la ligne correspondante augmente de 1 (sans dépasser le reste à recevoir). */
  async function scanItem(code) {
    let product
    try {
      product = (await api.get(`/products/barcode/${encodeURIComponent(code)}`)).data
    } catch (err) {
      return err.response?.status === 404 ? `Code inconnu : ${code}` : parseError(err).message
    }
    const line = open.find((l) => l.product_id === product.id)
    if (!line) return `« ${product.name} » n'est pas à recevoir dans cette commande.`
    const first = !scanning.current
    scanning.current = true
    let reached = false
    setQty((cur) => {
      const base = first ? Object.fromEntries(open.map((l) => [l.id, '0'])) : cur
      const next = Math.min(Number(base[line.id] ?? 0) + (product.scan_quantity ?? 1), remaining(line))
      reached = next >= remaining(line)
      return { ...base, [line.id]: String(next) }
    })
    return `${product.name} : +${fmtQty(product.scan_quantity ?? 1)}${reached ? ' (reste atteint)' : ''}`
  }

  function submit(e) {
    e.preventDefault()
    const items = open.map((l) => ({ line_id: l.id, quantity: qty[l.id] === '' ? 0 : qty[l.id] }))
    act(() => api.post(`/purchases/${po.id}/receive`, { items }),
      { confirm: 'Valider la réception ? Les quantités seront ajoutées au stock.' })
  }

  return (
    <section className="panel no-print">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2>Réception de marchandises</h2>
          <p className="muted" style={{ marginTop: 0 }}>
            Saisissez les quantités effectivement reçues (réception partielle possible), ou scannez les articles : chaque scan ajoute 1. Les produits sont ajoutés au stock.
          </p>
        </div>
        <ScanButton onScan={scanItem} continuous title="Scanner la marchandise reçue" label="Scanner la livraison" />
      </div>
      <form onSubmit={submit} noValidate>
        <table className="lines view">
          <thead>
            <tr><th>Désignation</th><th className="num">Commandé</th><th className="num">Déjà reçu</th>
              <th className="num">Reste</th><th className="num" style={{ width: 140 }}>Reçu maintenant</th></tr>
          </thead>
          <tbody>
            {open.map((l) => (
              <tr key={l.id}>
                <td>{l.description}</td>
                <td className="num">{fmtQty(l.quantity)}</td>
                <td className="num">{fmtQty(l.quantity_received)}</td>
                <td className="num">{fmtQty(remaining(l))} {l.unit}</td>
                <td><input className="num" type="number" min="0" max={remaining(l)} step="any"
                  aria-label={`Quantité reçue pour ${l.description}`} value={qty[l.id] ?? ''}
                  onChange={(e) => setQty({ ...qty, [l.id]: e.target.value })} /></td>
              </tr>
            ))}
          </tbody>
        </table>
        <div className="form-actions" style={{ marginTop: '1rem', marginBottom: 0 }}>
          <button className="btn btn-inline" disabled={busy}>Valider la réception</button>
        </div>
      </form>
    </section>
  )
}
