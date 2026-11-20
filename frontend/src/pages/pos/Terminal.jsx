import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api, { parseError } from '../../api'
import Modal from '../../components/Modal'
import ScanButton from '../../components/ScanButton'
import { fmtMoney, fmtQty } from '../../lib/format'
import { computeLine, computeTotals, tierPrice } from '../../lib/sales'
import PaymentDialog from './PaymentDialog'
import WeightDialog from './WeightDialog'

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
  const [weighing, setWeighing] = useState(null) // produit au poids en cours de pesée

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
      api.get('/products', { params: { q: q.trim() || undefined, active: 1, sellable: 1, per_page: 24 } })
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

  // Une ligne du panier = un produit dans une unité (unité de base ou conditionnement). Le stock se compte en unité de base.
  const lineKey = (c) => `${c.product.id}:${c.unit?.id ?? 0}`
  const factor = (c) => (c.unit ? Number(c.unit.quantity) : 1)
  const baseQty = (pid, list = cart) => list.filter((c) => c.product.id === pid && !c.unit).reduce((s, c) => s + (Number(c.quantity) || 0), 0)
  const usedStock = (pid, list = cart) => list.filter((c) => c.product.id === pid).reduce((s, c) => s + (Number(c.quantity) || 0) * factor(c), 0)
  // Prix : conditionnement, sinon palier atteint par la quantité totale du produit (mêmes règles que le serveur).
  const priceOf = (c, list = cart) => (c.unit ? Number(c.unit.sale_price) : tierPrice(c.product, baseQty(c.product.id, list)))
  const stockNotice = (p) => setNotice(`Stock disponible pour « ${p.name} » : ${fmtQty(p.stock_quantity)} ${p.unit}.`)

  const lines = useMemo(() => cart.map((c) => ({
    quantity: c.quantity, unit_price: priceOf(c), tax_rate: c.product.tax_rate, discount_rate: 0, by_weight: c.product.sold_by_weight,
  })), [cart]) // eslint-disable-line react-hooks/exhaustive-deps
  const totals = useMemo(() => computeTotals(lines), [lines])
  const validCart = cart.length > 0 && cart.every((c) => Number(c.quantity) > 0)

  /** Produit au poids : la quantité est le poids saisi (il remplace celui déjà dans le panier). */
  function setWeight(product, weight) {
    setWeighing(null)
    const i = cart.findIndex((c) => c.product.id === product.id)
    setCart(i < 0 ? [...cart, { product, quantity: weight }] : cart.map((c, j) => (j === i ? { ...c, quantity: weight } : c)))
  }

  /** Ajoute un produit pesé dont le poids vient d'une étiquette de balance (s'ajoute au poids déjà dans le panier). */
  function addWeighed(product) {
    const weight = Number(product.scan_quantity)
    const stock = Number(product.stock_quantity)
    const already = Number(cart.find((c) => c.product.id === product.id)?.quantity ?? 0)
    if (stock <= 0) return `« ${product.name} » est en rupture de stock.`
    if (already + weight > stock) return `Stock disponible pour « ${product.name} » : ${fmtQty(stock)} ${product.unit}.`
    setWeight(product, Math.round((already + weight) * 1000) / 1000)
    return `${product.name} : ${fmtQty(weight)} ${product.unit} (balance)`
  }

  function add(product, by = 1, unit = null) {
    const stock = Number(product.stock_quantity)
    if (stock <= 0) return setNotice(`« ${product.name} » est en rupture de stock.`)
    if (product.sold_by_weight) {
      // Un code-barres de balance peut déjà porter le poids ; sinon on demande la pesée.
      return by !== 1 ? setWeight(product, by) : setWeighing(product)
    }
    const line = { product, unit, quantity: by }
    if (usedStock(product.id) + by * factor(line) > stock) return stockNotice(product)
    const i = cart.findIndex((c) => lineKey(c) === lineKey(line))
    setCart(i < 0 ? [...cart, line] : cart.map((c, j) => (j === i ? { ...c, quantity: Number(c.quantity) + by } : c)))
  }

  function setQuantity(key, value) {
    const item = cart.find((c) => lineKey(c) === key)
    const stock = Number(item.product.stock_quantity)
    const others = usedStock(item.product.id) - (Number(item.quantity) || 0) * factor(item)
    const max = Math.max(0, Math.floor(((stock - others) / factor(item)) * 1000) / 1000)
    if (value !== '' && Number(value) > max) {
      stockNotice(item.product)
      value = String(max)
    }
    setCart(cart.map((c) => (lineKey(c) === key ? { ...c, quantity: value } : c)))
  }

  /** Change l'unité d'une ligne (pièce, carton…) ; si la ligne existe déjà dans cette unité, elles fusionnent. */
  function changeUnit(key, unitId) {
    const item = cart.find((c) => lineKey(c) === key)
    const unit = unitId ? item.product.units.find((u) => String(u.id) === String(unitId)) : null
    const moved = { ...item, unit }
    const rest = cart.filter((c) => lineKey(c) !== key)
    const twin = rest.find((c) => lineKey(c) === lineKey(moved))
    const next = twin
      ? [...rest.filter((c) => c !== twin), { ...twin, quantity: Number(twin.quantity) + Number(moved.quantity) }]
      : cart.map((c) => (lineKey(c) === key ? moved : c))
    if (usedStock(item.product.id, next) > Number(item.product.stock_quantity)) return stockNotice(item.product)
    setCart(next)
  }

  /** Code lu par la caméra : on retrouve le produit (code-barres ou référence exacte) et on l'ajoute au panier. */
  async function scanCode(code) {
    try {
      const { data } = await api.get(`/products/barcode/${encodeURIComponent(code)}`)
      // Étiquette de balance : le poids est dans le code, pas de pesée à refaire.
      if (data.scan_label === 'Balance') return addWeighed(data)
      // Code d'un conditionnement (carton…) : on ajoute un conditionnement, à son prix.
      if (data.scan_unit) {
        add(data, 1, data.scan_unit)
        return `${data.name} : 1 ${data.scan_unit.name}`
      }
      add(data, data.scan_quantity ?? 1)
      return `${data.name} ajouté${data.scan_quantity > 1 ? ` (× ${fmtQty(data.scan_quantity)})` : ''}`
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
    if (target) { add(target); setQ('') } else if (/^2[12]\d{11}$/.test(term)) {
      // Étiquette de balance tapée par un lecteur de code-barres.
      scanCode(term).then(setNotice)
      setQ('')
    } else setNotice('Aucun produit unique ne correspond : précisez la recherche.')
  }

  async function confirmSale(payments) {
    const { data } = await api.post('/pos/sales', {
      customer_id: customerId || null,
      lines: cart.map((c) => ({ product_id: c.product.id, quantity: Number(c.quantity), unit_id: c.unit?.id ?? null })),
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
                <span className="tile-price">{fmtMoney(p.sale_price)}{p.sold_by_weight ? ` / ${p.unit}` : ''}</span>
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
            <div className="cart-line" key={lineKey(c)}>
              <div className="cart-name">
                <strong>{c.product.name}</strong>
                <span className="muted small">
                  {fmtMoney(priceOf(c))} / {c.unit ? c.unit.name : c.product.unit}
                  {!c.unit && priceOf(c) < Number(c.product.sale_price) && <> <span className="pill pill-on">Palier</span></>}
                </span>
                {c.product.units?.length > 0 && !c.product.sold_by_weight && (
                  <select aria-label={`Unité de ${c.product.name}`} value={c.unit?.id ?? ''} onChange={(e) => changeUnit(lineKey(c), e.target.value)}
                    className="mt-1 w-auto rounded-lg border border-line bg-surface px-2 py-1 text-xs">
                    <option value="">{c.product.unit}</option>
                    {c.product.units.map((u) => <option key={u.id} value={u.id}>{u.name} ({fmtQty(u.quantity)})</option>)}
                  </select>
                )}
              </div>
              {c.product.sold_by_weight ? (
                <button type="button" className="btn btn-secondary btn-inline" onClick={() => setWeighing(c.product)}
                  aria-label={`Modifier le poids de ${c.product.name}`}>{fmtQty(c.quantity)} {c.product.unit}</button>
              ) : (
                <div className="cart-qty">
                  <button type="button" aria-label="Diminuer"
                    onClick={() => setQuantity(lineKey(c), String(Math.max(0, Number(c.quantity) - 1)))}>−</button>
                  <input type="number" min="0" step="any" inputMode="decimal" aria-label={`Quantité ${c.product.name}`}
                    value={c.quantity} onChange={(e) => setQuantity(lineKey(c), e.target.value)} />
                  <button type="button" aria-label="Augmenter" onClick={() => add(c.product, 1, c.unit)}>+</button>
                </div>
              )}
              <div className="cart-total">{fmtMoney(computeLine({ ...c, unit_price: priceOf(c), tax_rate: 0, by_weight: c.product.sold_by_weight }).net)}</div>
              <button type="button" className="link-btn danger" aria-label={`Retirer ${c.product.name}`}
                onClick={() => setCart(cart.filter((x) => lineKey(x) !== lineKey(c)))}>✕</button>
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

      <Modal open={Boolean(weighing)} onClose={() => setWeighing(null)} title="Pesée">
        {weighing && (
          <WeightDialog product={weighing} initial={cart.find((c) => c.product.id === weighing.id)?.quantity}
            onConfirm={(w) => setWeight(weighing, w)} onCancel={() => setWeighing(null)} />
        )}
      </Modal>

      <Modal open={paying} onClose={() => setPaying(false)} title="Encaissement">
        <PaymentDialog total={totals.total} walkIn={!customerId} onCancel={() => setPaying(false)} onConfirm={confirmSale} />
      </Modal>
    </div>
  )
}
