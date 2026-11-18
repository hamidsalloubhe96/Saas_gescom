import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api, { parseError } from '../../api'
import Modal from '../../components/Modal'
import ScanButton from '../../components/ScanButton'
import { fmtMoney, fmtQty } from '../../lib/format'
import { computeLine, computeTotals } from '../../lib/sales'
import PaymentDialog from './PaymentDialog'

export default function Terminal({ onSold }) {
  const navigate = useNavigate()
  const searchRef = useRef(null)
  const [q, setQ] = useState('')
  const [products, setProducts] = useState([])
  const [loadingP, setLoadingP] = useState(true)
  const [cart, setCart] = useState([]) // [{ product, quantity }]
  const [customers, setCustomers] = useState([])
  const [customerId, setCustomerId] = useState('')
  const [notice, setNotice] = useState(null)
  const [paying, setPaying] = useState(false)

  useEffect(() => { searchRef.current?.focus() }, [])

  useEffect(() => {
    api.get('/customers', { params: { per_page: 100, active: 1 } })
      .then(({ data }) => setCustomers(data.data.filter((c) => !c.is_walk_in)))
      .catch(() => {})
  }, [])

  // Recherche différée ; sans saisie, on affiche le début du catalogue.
  useEffect(() => {
    let cancelled = false
    setLoadingP(true)
    const t = setTimeout(() => {
      api.get('/products', { params: { q: q.trim() || undefined, active: 1, per_page: 24 } })
        .then(({ data }) => { if (!cancelled) setProducts(data.data) })
        .catch((err) => { if (!cancelled) setNotice(parseError(err).message) })
        .finally(() => { if (!cancelled) setLoadingP(false) })
    }, 250)
    return () => { cancelled = true; clearTimeout(t) }
  }, [q])

  useEffect(() => {
    if (!notice) return undefined
    const t = setTimeout(() => setNotice(null), 3500)
    return () => clearTimeout(t)
  }, [notice])

  const lines = useMemo(() => cart.map((c) => ({
    quantity: c.quantity, unit_price: c.product.sale_price, tax_rate: c.product.tax_rate, discount_rate: 0,
  })), [cart])
  const totals = useMemo(() => computeTotals(lines), [lines])
  const validCart = cart.length > 0 && cart.every((c) => Number(c.quantity) > 0)

  function add(product) {
    const stock = Number(product.stock_quantity)
    if (stock <= 0) return setNotice(`« ${product.name} » est en rupture de stock.`)
    const i = cart.findIndex((c) => c.product.id === product.id)
    if (i < 0) return setCart([...cart, { product, quantity: 1 }])
    const next = Number(cart[i].quantity) + 1
    if (next > stock) return setNotice(`Stock disponible pour « ${product.name} » : ${fmtQty(stock)}.`)
    setCart(cart.map((c, j) => (j === i ? { ...c, quantity: next } : c)))
  }

  function setQuantity(id, value) {
    const item = cart.find((c) => c.product.id === id)
    const stock = Number(item.product.stock_quantity)
    if (value !== '' && Number(value) > stock) {
      setNotice(`Stock disponible pour « ${item.product.name} » : ${fmtQty(stock)}.`)
      value = String(stock)
    }
    setCart(cart.map((c) => (c.product.id === id ? { ...c, quantity: value } : c)))
  }

  /** Code lu par la caméra : on retrouve le produit (code-barres ou référence exacte) et on l'ajoute au panier. */
  async function scanCode(code) {
    try {
      const { data } = await api.get(`/products/barcode/${encodeURIComponent(code)}`)
      add(data)
      return `${data.name} ajouté`
    } catch (err) {
      return err.response?.status === 404 ? `Code inconnu : ${code}` : parseError(err).message
    }
  }

  function onSearchKey(e) {
    if (e.key !== 'Enter') return
    e.preventDefault()
    const term = q.trim().toLowerCase()
    if (!term) return
    // Lecteur de code-barres : correspondance exacte code-barres / référence, sinon résultat unique.
    const exact = products.find((p) => [p.barcode, p.sku].some((v) => v && v.toLowerCase() === term))
    const target = exact ?? (products.length === 1 ? products[0] : null)
    if (target) { add(target); setQ('') } else setNotice('Aucun produit unique ne correspond : précisez la recherche.')
  }

  async function confirmSale(payments) {
    const { data } = await api.post('/pos/sales', {
      customer_id: customerId || null,
      lines: cart.map((c) => ({ product_id: c.product.id, quantity: Number(c.quantity) })),
      payments,
    })
    setPaying(false)
    onSold()
    navigate(`/caisse/ticket/${data.document.id}`, { state: { change: data.change_due, tendered: data.tendered } })
  }

  return (
    <div className="pos">
      <section className="pos-catalog">
        <div className="mb-3 flex gap-2">
          <input ref={searchRef} type="search" className="pos-search !mb-0 flex-1" placeholder="Rechercher ou scanner un code-barres…"
            aria-label="Rechercher un produit" value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={onSearchKey} />
          <ScanButton onScan={scanCode} continuous title="Scanner les articles" className="min-h-12" />
        </div>
        {notice && <div role="status" className="alert pos-notice">{notice}</div>}
        <div className="pos-grid" aria-busy={loadingP}>
          {products.map((p) => {
            const out = Number(p.stock_quantity) <= 0
            return (
              <button key={p.id} type="button" className={`tile ${out ? 'tile-out' : ''}`} onClick={() => add(p)}>
                <span className="tile-name">{p.name}</span>
                <span className="tile-price">{fmtMoney(p.sale_price)}</span>
                <span className="tile-stock">{out ? 'Rupture' : `Stock : ${fmtQty(p.stock_quantity)} ${p.unit}`}</span>
              </button>
            )
          })}
          {!loadingP && products.length === 0 && <p className="muted">Aucun produit trouvé.</p>}
        </div>
      </section>

      <aside className="pos-cart" aria-label="Panier">
        <h2>Panier</h2>
        <div className="cart-lines">
          {cart.map((c) => (
            <div className="cart-line" key={c.product.id}>
              <div className="cart-name">
                <strong>{c.product.name}</strong>
                <span className="muted small">{fmtMoney(c.product.sale_price)} / {c.product.unit}</span>
              </div>
              <div className="cart-qty">
                <button type="button" aria-label="Diminuer"
                  onClick={() => setQuantity(c.product.id, String(Math.max(0, Number(c.quantity) - 1)))}>−</button>
                <input type="number" min="0" step="any" inputMode="decimal" aria-label={`Quantité ${c.product.name}`}
                  value={c.quantity} onChange={(e) => setQuantity(c.product.id, e.target.value)} />
                <button type="button" aria-label="Augmenter" onClick={() => add(c.product)}>+</button>
              </div>
              <div className="cart-total">{fmtMoney(computeLine({ ...c, unit_price: c.product.sale_price, tax_rate: 0 }).net)}</div>
              <button type="button" className="link-btn danger" aria-label={`Retirer ${c.product.name}`}
                onClick={() => setCart(cart.filter((x) => x.product.id !== c.product.id))}>✕</button>
            </div>
          ))}
          {cart.length === 0 && <p className="muted empty-cart">Le panier est vide.<br />Touchez un produit pour l'ajouter.</p>}
        </div>

        <div className="field">
          <label htmlFor="pos-customer">Client</label>
          <select id="pos-customer" value={customerId} onChange={(e) => setCustomerId(e.target.value)}>
            <option value="">Client comptoir (anonyme)</option>
            {customers.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
        </div>

        <dl className="totals">
          <div><dt>Total HT</dt><dd>{fmtMoney(totals.subtotal)}</dd></div>
          <div><dt>TVA</dt><dd>{fmtMoney(totals.tax)}</dd></div>
          <div className="grand"><dt>Total TTC</dt><dd>{fmtMoney(totals.total)}</dd></div>
        </dl>

        <div className="cart-actions">
          <button type="button" className="btn btn-secondary" disabled={cart.length === 0} onClick={() => setCart([])}>Vider</button>
          <button type="button" className="btn btn-pay" disabled={!validCart} onClick={() => setPaying(true)}>
            Encaisser {validCart ? fmtMoney(totals.total) : ''}
          </button>
        </div>
      </aside>

      <Modal open={paying} onClose={() => setPaying(false)} title="Encaissement">
        <PaymentDialog total={totals.total} walkIn={!customerId} onCancel={() => setPaying(false)} onConfirm={confirmSale} />
      </Modal>
    </div>
  )
}
