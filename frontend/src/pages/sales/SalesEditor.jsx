import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import api, { parseError } from '../../api'
import LineCards from '../../components/LineCards'
import { fmtMoney } from '../../lib/format'
import { TYPE_LABEL, addDays, computeLine, computeTotals, describeFieldErrors, tierPrice, today } from '../../lib/sales'

const blankLine = (key) => ({
  key, product_id: '', unit_id: '', price_auto: false, description: '', unit: '', quantity: 1, unit_price: '', discount_rate: '', tax_rate: '',
})

export default function SalesEditor() {
  const { id } = useParams()
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const isEdit = Boolean(id)
  const keySeq = useRef(1)
  const nextKey = () => keySeq.current++

  const [type, setType] = useState(params.get('type') === 'invoice' ? 'invoice' : 'quote')
  const [form, setForm] = useState({
    customer_id: '', issue_date: today(), due_date: addDays(today(), 30), notes: '',
  })
  const [lines, setLines] = useState(() => [blankLine(0)])
  const [customers, setCustomers] = useState([])
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [errors, setErrors] = useState([])
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    let cancelled = false
    async function init() {
      try {
        const [c, p] = await Promise.all([
          api.get('/customers', { params: { per_page: 100, active: 1 } }),
          api.get('/products', { params: { per_page: 100, active: 1 } }),
        ])
        if (cancelled) return
        setCustomers(c.data.data)
        setProducts(p.data.data)
        if (isEdit) {
          const { data: d } = await api.get(`/documents/${id}`)
          if (cancelled) return
          setType(d.type)
          setForm({ customer_id: d.customer_id, issue_date: d.issue_date, due_date: d.due_date ?? '', notes: d.notes ?? '' })
          setLines(d.lines.map((l) => ({
            // Le conditionnement d'une ligne enregistrée est retrouvé par son nom ; son prix enregistré est conservé.
            key: nextKey(), product_id: l.product_id ?? '', price_auto: false,
            unit_id: p.data.data.find((x) => x.id === l.product_id)?.units?.find((u) => u.name === l.unit)?.id ?? '',
            description: l.description, unit: l.unit ?? '',
            quantity: l.quantity, unit_price: l.unit_price, discount_rate: l.discount_rate, tax_rate: l.tax_rate,
          })))
          // Le client d'un document peut avoir été désactivé depuis : on le garde dans la liste.
          if (!c.data.data.some((x) => x.id === d.customer_id)) setCustomers((cs) => [...cs, d.customer])
        }
      } catch (err) {
        setErrors([parseError(err).message])
      } finally {
        if (!cancelled) setLoading(false)
      }
    }
    init()
    return () => { cancelled = true }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  const totals = useMemo(() => computeTotals(lines), [lines])

  const setField = (e) => setForm({ ...form, [e.target.name]: e.target.value })
  const productOf = (l) => products.find((x) => String(x.id) === String(l.product_id))

  /** Lignes dont le prix n'a pas été saisi à la main : prix du conditionnement, ou du palier atteint par la quantité totale du produit. */
  const reprice = (ls) => ls.map((l) => {
    const p = productOf(l)
    if (!l.price_auto || !p) return l
    if (l.unit_id) {
      const u = p.units?.find((x) => String(x.id) === String(l.unit_id))
      return u ? { ...l, unit_price: u.sale_price } : l
    }
    const qty = ls.filter((x) => String(x.product_id) === String(l.product_id) && !x.unit_id).reduce((s, x) => s + (Number(x.quantity) || 0), 0)
    return { ...l, unit_price: tierPrice(p, qty) }
  })

  const setLine = (key, patch) => setLines((ls) => reprice(ls.map((l) => (l.key === key ? { ...l, ...patch } : l))))

  function pickProduct(key, productId) {
    const p = products.find((x) => String(x.id) === String(productId))
    if (!p) return setLine(key, { product_id: '', unit_id: '', price_auto: false })
    setLine(key, {
      product_id: p.id, unit_id: '', price_auto: true, description: p.name, unit: p.unit, unit_price: p.sale_price, tax_rate: p.tax_rate,
    })
  }

  async function submit(e) {
    e.preventDefault()
    setSaving(true)
    setErrors([])
    const payload = {
      type, ...form,
      due_date: form.due_date || null,
      lines: lines.map(({ key, price_auto, ...l }) => ({ ...l, product_id: l.product_id || null, unit_id: l.unit_id || null })),
    }
    if (isEdit) delete payload.type
    try {
      const { data } = isEdit ? await api.put(`/documents/${id}`, payload) : await api.post('/documents', payload)
      navigate(`/ventes/${data.id}`, { replace: true })
    } catch (err) {
      const parsed = parseError(err)
      const list = describeFieldErrors(parsed.fields)
      setErrors(list.length ? list : [parsed.message])
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } finally {
      setSaving(false)
    }
  }

  if (loading) return <div className="splash" role="status">Chargement…</div>

  const label = TYPE_LABEL[type]
  return (
    <form onSubmit={submit} noValidate className="max-lg:pb-24">
      <header className="page-head page-head-row">
        <div>
          <h1>{isEdit ? `Modifier le ${label.toLowerCase()}` : `${type === 'quote' ? 'Nouveau' : 'Nouvelle'} ${label.toLowerCase()}`}</h1>
          <p className="muted">Les totaux sont recalculés par le serveur à l'enregistrement.</p>
        </div>
      </header>

      {errors.length > 0 && (
        <div role="alert" className="alert">
          <ul className="alert-list">{errors.map((m) => <li key={m}>{m}</li>)}</ul>
        </div>
      )}

      <section className="panel">
        <div className="row">
          <div className="field">
            <label htmlFor="customer_id">Client *</label>
            <select id="customer_id" name="customer_id" value={form.customer_id} onChange={setField} required>
              <option value="">— Choisir un client —</option>
              {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
            <p className="field-hint">
              {customers.length === 0 ? <>Aucun client actif. <Link to="/clients">Créer un client</Link></> : ' '}
            </p>
          </div>
          <div className="field">
            <label htmlFor="issue_date">Date *</label>
            <input id="issue_date" name="issue_date" type="date" value={form.issue_date} onChange={setField} required />
            <p className="field-hint">&nbsp;</p>
          </div>
          <div className="field">
            <label htmlFor="due_date">{type === 'quote' ? 'Valable jusqu\'au' : 'Échéance'}</label>
            <input id="due_date" name="due_date" type="date" value={form.due_date} min={form.issue_date} onChange={setField} />
            <p className="field-hint">&nbsp;</p>
          </div>
        </div>
      </section>

      <section className="panel">
        <div className="lg:hidden">
          <LineCards lines={lines} products={products} onPick={pickProduct} onChange={setLine} withDiscount
            onPrice={(key, v) => setLine(key, { unit_price: v, price_auto: false })}
            onRemove={(key) => setLines(lines.filter((x) => x.key !== key))}
            net={(l) => computeLine({ ...l, by_weight: productOf(l)?.sold_by_weight }).net}
            renderBadge={(l) => (l.price_auto && !l.unit_id && productOf(l) && Number(l.unit_price) < Number(productOf(l).sale_price) ? <span className="pill pill-on">Palier</span> : null)}
            renderUnit={(l, i) => (productOf(l)?.units?.length > 0 ? (
              <select aria-label={`Unité ligne ${i + 1}`} value={l.unit_id} className="mt-2 w-full rounded-lg border border-line bg-surface px-3 py-2.5 text-base"
                onChange={(e) => {
                  const u = productOf(l).units.find((x) => String(x.id) === e.target.value)
                  setLine(l.key, { unit_id: e.target.value, unit: u ? u.name : productOf(l).unit, price_auto: true })
                }}>
                <option value="">{productOf(l).unit}</option>
                {productOf(l).units.map((u) => <option key={u.id} value={u.id}>{u.name} ({u.quantity})</option>)}
              </select>
            ) : null)} />
        </div>
        <div className="table-wrap flat max-lg:hidden">
          <table className="lines">
            <thead>
              <tr>
                <th style={{ width: '20%' }}>Produit</th><th>Désignation *</th><th className="num">Qté *</th>
                <th className="num">Prix unit. *</th><th className="num">Remise %</th><th className="num">TVA %</th>
                <th className="num">Total HT</th><th />
              </tr>
            </thead>
            <tbody>
              {lines.map((l, i) => (
                <tr key={l.key}>
                  <td>
                    <select aria-label={`Produit ligne ${i + 1}`} value={l.product_id}
                      onChange={(e) => pickProduct(l.key, e.target.value)}>
                      <option value="">Ligne libre</option>
                      {products.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                    </select>
                    {productOf(l)?.units?.length > 0 && (
                      <select aria-label={`Unité ligne ${i + 1}`} value={l.unit_id} className="mt-1"
                        onChange={(e) => {
                          const u = productOf(l).units.find((x) => String(x.id) === e.target.value)
                          setLine(l.key, { unit_id: e.target.value, unit: u ? u.name : productOf(l).unit, price_auto: true })
                        }}>
                        <option value="">{productOf(l).unit}</option>
                        {productOf(l).units.map((u) => <option key={u.id} value={u.id}>{u.name} ({u.quantity})</option>)}
                      </select>
                    )}
                  </td>
                  <td><input aria-label={`Désignation ligne ${i + 1}`} value={l.description}
                    onChange={(e) => setLine(l.key, { description: e.target.value })} maxLength={255} /></td>
                  <td><input className="num" type="number" min="0" step="any" aria-label={`Quantité ligne ${i + 1}`}
                    value={l.quantity} onChange={(e) => setLine(l.key, { quantity: e.target.value })} /></td>
                  <td><input className="num" type="number" min="0" step="0.01" aria-label={`Prix ligne ${i + 1}`}
                    value={l.unit_price} onChange={(e) => setLine(l.key, { unit_price: e.target.value, price_auto: false })} /></td>
                  <td><input className="num" type="number" min="0" max="100" step="0.01" aria-label={`Remise ligne ${i + 1}`}
                    value={l.discount_rate} onChange={(e) => setLine(l.key, { discount_rate: e.target.value })} /></td>
                  <td><input className="num" type="number" min="0" max="100" step="0.01" aria-label={`TVA ligne ${i + 1}`}
                    value={l.tax_rate} onChange={(e) => setLine(l.key, { tax_rate: e.target.value })} /></td>
                  <td className="num">{fmtMoney(computeLine({ ...l, by_weight: productOf(l)?.sold_by_weight }).net)}</td>
                  <td>
                    <button type="button" className="link-btn danger" aria-label={`Supprimer la ligne ${i + 1}`}
                      disabled={lines.length === 1} onClick={() => setLines(lines.filter((x) => x.key !== l.key))}>✕</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <button type="button" className="btn btn-secondary btn-inline" style={{ marginTop: '.75rem' }}
          onClick={() => setLines([...lines, blankLine(nextKey())])}>+ Ajouter une ligne</button>

        <dl className="totals">
          <div><dt>Total HT</dt><dd>{fmtMoney(totals.subtotal)}</dd></div>
          <div><dt>TVA</dt><dd>{fmtMoney(totals.tax)}</dd></div>
          <div className="grand"><dt>Total TTC</dt><dd>{fmtMoney(totals.total)}</dd></div>
        </dl>
      </section>

      <section className="panel">
        <div className="field">
          <label htmlFor="notes">Notes / conditions</label>
          <textarea id="notes" name="notes" rows="3" value={form.notes} onChange={setField} maxLength={2000} />
        </div>
      </section>

      <div className="form-actions">
        <Link to={isEdit ? `/ventes/${id}` : `/ventes?type=${type}`} className="btn btn-secondary btn-inline btn-link">Annuler</Link>
        <button className="btn btn-inline max-lg:hidden" disabled={saving}>{saving ? 'Enregistrement…' : 'Enregistrer'}</button>
      </div>
      {/* Téléphone : le total et « Enregistrer » restent visibles en bas pendant la saisie. */}
      <div className="fixed inset-x-0 bottom-0 z-30 flex items-center gap-2.5 border-t border-line bg-surface px-3 pt-2.5 pb-[max(0.625rem,env(safe-area-inset-bottom))] shadow-[0_-4px_12px_rgba(0,0,0,.08)] lg:hidden">
        <div className="min-w-0 flex-1 leading-tight">
          <strong className="block text-sm tabular-nums">Total TTC {fmtMoney(totals.total)}</strong>
          <span className="text-xs text-muted">{lines.length} {lines.length > 1 ? 'lignes' : 'ligne'}</span>
        </div>
        <button className="btn btn-inline min-h-11 shrink-0" disabled={saving}>{saving ? 'Enregistrement…' : 'Enregistrer'}</button>
      </div>
    </form>
  )
}
