import { useEffect, useState } from 'react'
import api, { parseError } from '../../api'
import { fmtDate, fmtMoney } from '../../lib/format'

export default function RegisterHistory({ refreshKey }) {
  const [rows, setRows] = useState([])
  const [page, setPage] = useState(1)
  const [last, setLast] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    api.get('/pos/sessions', { params: { page } })
      .then(({ data }) => { if (!cancelled) { setRows(data.data); setLast(data.last_page) } })
      .catch((err) => { if (!cancelled) setError(parseError(err).message) })
      .finally(() => { if (!cancelled) setLoading(false) })
    return () => { cancelled = true }
  }, [page, refreshKey])

  return (
    <>
      {error && <div role="alert" className="alert">{error}</div>}
      <div className="table-wrap" aria-busy={loading}>
        <table>
          <thead>
            <tr><th>Ouverture</th><th>Clôture</th><th>Caissier</th><th className="num">Fond</th>
              <th className="num">Ventes</th><th className="num">Attendu</th><th className="num">Compté</th><th className="num">Écart</th></tr>
          </thead>
          <tbody>
            {rows.map((s) => {
              const diff = s.difference === null ? null : Number(s.difference)
              return (
                <tr key={s.id}>
                  <td>{fmtDate(s.opened_at)}</td>
                  <td>{s.closed_at ? fmtDate(s.closed_at) : <span className="pill pill-info">Ouverte</span>}</td>
                  <td>{s.opener?.name ?? '—'}</td>
                  <td className="num">{fmtMoney(s.opening_amount)}</td>
                  <td className="num">{s.total_sales === null ? '—' : fmtMoney(s.total_sales)}</td>
                  <td className="num">{s.expected_cash === null ? '—' : fmtMoney(s.expected_cash)}</td>
                  <td className="num">{s.counted_cash === null ? '—' : fmtMoney(s.counted_cash)}</td>
                  <td className={`num ${diff === null || diff === 0 ? '' : diff < 0 ? 'qty-neg' : 'qty-pos'}`}>
                    {diff === null ? '—' : `${diff > 0 ? '+' : ''}${fmtMoney(diff)}`}
                  </td>
                </tr>
              )
            })}
            {!loading && rows.length === 0 && <tr><td colSpan="8" className="empty">Aucune session de caisse.</td></tr>}
            {loading && rows.length === 0 && <tr><td colSpan="8" className="empty">Chargement…</td></tr>}
          </tbody>
        </table>
      </div>
      {last > 1 && (
        <nav className="pager" aria-label="Pagination">
          <button disabled={page <= 1} onClick={() => setPage(page - 1)}>← Précédent</button>
          <span>Page {page} / {last}</span>
          <button disabled={page >= last} onClick={() => setPage(page + 1)}>Suivant →</button>
        </nav>
      )}
    </>
  )
}
