import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import api, { parseError } from '../../api'
import LineCards from '../../components/LineCards'
import { fmtMoney } from '../../lib/format'
import { addDays, computeLine, computeTotals, describeFieldErrors, today } from '../../lib/sales'

const blankLine = (key) => ({
  key, product_id: '', description: '', unit: '', quantity: 1, unit_price: '', tax_rate: '',
})

export default function PurchaseEditor() {
  const { id } = useParams()
  const navigate = useNavigate()
  const isEdit = Boolean(id)
  const keySeq = useRef(1)
  const nextKey = () => keySeq.current++

  const [form, setForm] = useState({
    supplier_id: '', order_date: today(), expected_date: addDays(today(), 7), notes: '',
  })
  const [lines, setLines] = useState(() => [blankLine(0)])
  const [suppliers, setSuppliers] = useState([])
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [errors, setErrors] = useState([])
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    let cancelled = false
    async function init() {
      try {
        const [s, p] = await Promise.all([
          api.get('/suppliers', { params: { per_page: 100, active: 1 } }),
          api.get('/products', { params: { per_page: 100, active: 1 } }),
        ])
        if (cancelled) return
        setSuppliers(s.data.data)
        setProducts(p.data.data)
        if (isEdit) {
          const { data: d } = await api.get(`/purchases/${id}`)
          if (cancelled) return
          setForm({ supplier_id: d.supplier_id, order_date: d.order_date, expected_date: d.expected_date ?? '', notes: d.notes ?? '' })
          setLines(d.lines.map((l) => ({
            key: nextKey(), product_id: l.product_id ?? '', description: l.description, unit: l.unit ?? '',
            quantity: l.quantity, unit_price: l.unit_price, tax_rate: l.tax_rate,
          })))
          if (!s.data.data.some((x) => x.id === d.supplier_id)) setSuppliers((x) => [...x, d.supplier])
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
  const setLine = (key, patch) => setLines((ls) => ls.map((l) => (l.key === key ? { ...l, ...patch } : l)))

  function pickProduct(key, productId) {
    const p = products.find((x) => String(x.id) === String(productId))
    if (!p) return setLine(key, { product_id: '' })
    // Pour un achat, on propose le prix d'achat du produit.
    setLine(key, {
      product_id: p.id, description: p.name, unit: p.unit, unit_price: p.purchase_price, tax_rate: p.tax_rate,
    })
  }

  async function submit(e) {
    e.preventDefault()
    setSaving(true)
    setErrors([])
    const payload = {
      ...form,
      expected_date: form.expected_date || null,
      lines: lines.map(({ key, ...l }) => ({ ...l, product_id: l.product_id || null })),
    }
    try {
      const { data } = isEdit ? await api.put(`/purchases/${id}`, payload) : await api.post('/purchases', payload)
      navigate(`/achats/${data.id}`, { replace: true })
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

  return (
    <form onSubmit={submit} noValidate className="max-lg:pb-24">
      <header className="page-head">
        <h1>{isEdit ? 'Modifier la commande' : 'Nouvelle commande fournisseur'}</h1>
        <p className="muted">Les totaux sont recalculés par le serveur à l'enregistrement.</p>
      </header>

      {errors.length > 0 && (
        <div role="alert" className="alert">
          <ul className="alert-list">{errors.map((m) => <li key={m}>{m}</li>)}</ul>
        </div>
      )}

      <section className="panel">
        <div className="row">
          <div className="field">
            <label htmlFor="supplier_id">Fournisseur *</label>
            <select id="supplier_id" name="supplier_id" value={form.supplier_id} onChange={setField} required>
              <option value="">— Choisir un fournisseur —</option>
              {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </select>
            <p className="field-hint">
              {suppliers.length === 0 ? <>Aucun fournisseur actif. <Link to="/fournisseurs">Créer un fournisseur</Link></> : ' '}
            </p>
          </div>
          <div className="field">
            <label htmlFor="order_date">Date de commande *</label>
            <input id="order_date" name="order_date" type="date" value={form.order_date} onChange={setField} required />
            <p className="field-hint">&nbsp;</p>
          </div>
          <div className="field">
            <label htmlFor="expected_date">Livraison prévue</label>
            <input id="expected_date" name="expected_date" type="date" value={form.expected_date}
              min={form.order_date} onChange={setField} />
            <p className="field-hint">&nbsp;</p>
          </div>
        </div>
      </section>

      <section className="panel">
        <div className="lg:hidden">
          <LineCards lines={lines} products={products} onPick={pickProduct} onChange={setLine} priceLabel="Prix d'achat"
            onPrice={(key, v) => setLine(key, { unit_price: v })}
            onRemove={(key) => setLines(lines.filter((x) => x.key !== key))}
            net={(l) => computeLine({ ...l, discount_rate: 0 }).net} />
        </div>
        <div className="table-wrap flat max-lg:hidden">
          <table className="lines">
            <thead>
              <tr>
                <th style={{ width: '22%' }}>Produit</th><th>Désignation *</th><th className="num">Qté *</th>
                <th className="num">Prix d'achat *</th><th className="num">TVA %</th><th className="num">Total HT</th><th />
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
                  </td>
                  <td><input aria-label={`Désignation ligne ${i + 1}`} value={l.description}
                    onChange={(e) => setLine(l.key, { description: e.target.value })} maxLength={255} /></td>
                  <td><input className="num" type="number" min="0" step="any" aria-label={`Quantité ligne ${i + 1}`}
                    value={l.quantity} onChange={(e) => setLine(l.key, { quantity: e.target.value })} /></td>
                  <td><input className="num" type="number" min="0" step="0.01" aria-label={`Prix ligne ${i + 1}`}
                    value={l.unit_price} onChange={(e) => setLine(l.key, { unit_price: e.target.value })} /></td>
                  <td><input className="num" type="number" min="0" max="100" step="0.01" aria-label={`TVA ligne ${i + 1}`}
                    value={l.tax_rate} onChange={(e) => setLine(l.key, { tax_rate: e.target.value })} /></td>
                  <td className="num">{fmtMoney(computeLine({ ...l, discount_rate: 0 }).net)}</td>
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
        <Link to={isEdit ? `/achats/${id}` : '/achats'} className="btn btn-secondary btn-inline btn-link">Annuler</Link>
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
