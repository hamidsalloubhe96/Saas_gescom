import { useEffect, useState } from 'react'
import api, { parseError } from '../../api'
import { fmtDate, fmtQty } from '../../lib/format'

const LABEL = { in: 'Entrée', out: 'Sortie', adjust: 'Inventaire' }

export default function History({ product, onClose }) {
  const [rows, setRows] = useState([])
  const [page, setPage] = useState(1)
  const [last, setLast] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    api.get(`/products/${product.id}/movements`, { params: { page } })
      .then(({ data }) => { if (!cancelled) { setRows(data.data); setLast(data.last_page) } })
      .catch((err) => { if (!cancelled) setError(parseError(err).message) })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [product.id, page])

  return (
    <>
      <p className="muted" style={{ marginTop: 0 }}>{product.name}</p>
      {error && <div role="alert" className="alert">{error}</div>}
      <div className="table-wrap" aria-busy={loading}>
        <table>
          <thead>
            <tr><th>Date</th><th>Type</th><th className="num">Variation</th><th className="num">Stock</th><th>Motif</th><th>Par</th></tr>
          </thead>
          <tbody>
            {rows.map((m) => (
              <tr key={m.id}>
                <td>{fmtDate(m.created_at)}</td>
                <td>{LABEL[m.type] ?? m.type}</td>
                <td className={`num ${Number(m.quantity) >= 0 ? 'qty-pos' : 'qty-neg'}`}>
                  {Number(m.quantity) > 0 ? '+' : ''}{fmtQty(m.quantity)}
                </td>
                <td className="num">{fmtQty(m.balance_after)}</td>
                <td>{m.reason || '—'}</td>
                <td>{m.user?.name ?? '—'}</td>
              </tr>
            ))}
            {!loading && rows.length === 0 && <tr><td colSpan="6" className="empty">Aucun mouvement.</td></tr>}
            {loading && rows.length === 0 && <tr><td colSpan="6" className="empty">Chargement…</td></tr>}
          </tbody>
        </table>
      </div>
      {last > 1 && (
        <nav className="pager" aria-label="Pagination historique">
          <button disabled={page <= 1} onClick={() => setPage(page - 1)}>← Précédent</button>
          <span>Page {page} / {last}</span>
          <button disabled={page >= last} onClick={() => setPage(page + 1)}>Suivant →</button>
        </nav>
      )}
      <div className="modal-actions" style={{ marginTop: '1rem' }}>
        <button type="button" className="btn btn-secondary" onClick={onClose}>Fermer</button>
      </div>
    </>
  )
}
