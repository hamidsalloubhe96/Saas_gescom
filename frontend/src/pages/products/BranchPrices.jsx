import { useEffect, useState } from 'react'
import api, { parseError } from '../../api'
import { fmtMoney, fmtQty } from '../../lib/format'

/** Stock et prix local d'un produit dans chaque point de vente (siège). Prix vide = prix de base. */
export default function BranchPrices({ product, onClose }) {
  const [data, setData] = useState(null)
  const [prices, setPrices] = useState({})
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(null)

  useEffect(() => {
    api.get(`/products/${product.id}/branches`).then(({ data: d }) => {
      setData(d)
      setPrices(Object.fromEntries(d.branches.map((b) => [b.id, b.local_price ?? ''])))
    }).catch((e) => setError(parseError(e).message))
  }, [product.id])

  async function save(b) {
    setBusy(b.id); setError(null)
    try {
      const value = prices[b.id] === '' ? null : Number(prices[b.id])
      const { data: d } = await api.put(`/products/${product.id}/branch-prices`, { branch_id: b.id, sale_price: value })
      setData(d)
    } catch (err) { setError(parseError(err).message) } finally { setBusy(null) }
  }

  if (!data) return error ? <div role="alert" className="alert">{error}</div> : <p className="muted" role="status">Chargement…</p>

  return (
    <>
      {error && <div role="alert" className="alert">{error}</div>}
      <p className="muted">{product.name} · prix de base : <strong>{fmtMoney(data.base_price)}</strong></p>
      <table>
        <thead><tr><th>Point de vente</th><th className="num">Stock</th><th className="num">Prix local</th><th /></tr></thead>
        <tbody>
          {data.branches.map((b) => (
            <tr key={b.id}>
              <td>{b.name} {b.is_main && <span className="pill pill-info">Siège</span>}</td>
              <td className="num">{fmtQty(b.stock)}</td>
              <td className="num"><input aria-label={`Prix local à ${b.name}`} type="number" min="0" step="any" placeholder={String(data.base_price)} value={prices[b.id]}
                onChange={(e) => setPrices({ ...prices, [b.id]: e.target.value })} style={{ width: '8rem' }} /></td>
              <td><button className="btn btn-secondary btn-inline" disabled={busy === b.id} onClick={() => save(b)}>Enregistrer</button></td>
            </tr>
          ))}
        </tbody>
      </table>
      <div className="modal-actions"><button className="btn" onClick={onClose}>Fermer</button></div>
    </>
  )
}
