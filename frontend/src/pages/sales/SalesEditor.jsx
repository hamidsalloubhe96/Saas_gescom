import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import api, { parseError } from '../../api'
import { fmtMoney } from '../../lib/format'
import { TYPE_LABEL, addDays, computeLine, computeTotals, describeFieldErrors, today } from '../../lib/sales'

const blankLine = (key) => ({
  key, product_id: '', description: '', unit: '', quantity: 1, unit_price: '', discount_rate: '', tax_rate: '',
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
            key: nextKey(), product_id: l.product_id ?? '', description: l.description, unit: l.unit ?? '',
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
  const setLine = (key, patch) => setLines((ls) => ls.map((l) => (l.key === key ? { ...l, ...patch } : l)))

  function pickProduct(key, productId) {
    const p = products.find((x) => String(x.id) === String(productId))
    if (!p) return setLine(key, { product_id: '' })
    setLine(key, {
      product_id: p.id, description: p.name, unit: p.unit, unit_price: p.sale_price, tax_rate: p.tax_rate,
    })
  }

  async function submit(e) {
    e.preventDefault()
    setSaving(true)
    setErrors([])
    const payload = {
      type, ...form,
      due_date: form.due_date || null,
      lines: lines.map(({ key, ...l }) => ({ ...l, product_id: l.product_id || null })),
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
    <form onSubmit={submit} noValidate>
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
        <div className="table-wrap flat">
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
                  </td>
                  <td><input aria-label={`Désignation ligne ${i + 1}`} value={l.description}
                    onChange={(e) => setLine(l.key, { description: e.target.value })} maxLength={255} /></td>
                  <td><input className="num" type="number" min="0" step="any" aria-label={`Quantité ligne ${i + 1}`}
                    value={l.quantity} onChange={(e) => setLine(l.key, { quantity: e.target.value })} /></td>
                  <td><input className="num" type="number" min="0" step="0.01" aria-label={`Prix ligne ${i + 1}`}
                    value={l.unit_price} onChange={(e) => setLine(l.key, { unit_price: e.target.value })} /></td>
                  <td><input className="num" type="number" min="0" max="100" step="0.01" aria-label={`Remise ligne ${i + 1}`}
                    value={l.discount_rate} onChange={(e) => setLine(l.key, { discount_rate: e.target.value })} /></td>
                  <td><input className="num" type="number" min="0" max="100" step="0.01" aria-label={`TVA ligne ${i + 1}`}
                    value={l.tax_rate} onChange={(e) => setLine(l.key, { tax_rate: e.target.value })} /></td>
                  <td className="num">{fmtMoney(computeLine(l).net)}</td>
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
        <button className="btn btn-inline" disabled={saving}>{saving ? 'Enregistrement…' : 'Enregistrer'}</button>
      </div>
    </form>
  )
}
