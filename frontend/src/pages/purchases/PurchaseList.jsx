import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { AddIcon } from '../../components/ui'
import api, { parseError } from '../../api'
import { useAuth } from '../../auth'
import PurchasePill from '../../components/PurchasePill'
import { fmtMoney } from '../../lib/format'
import { PURCHASE_STATUS, paymentState } from '../../lib/purchases'
import { fmtDay } from '../../lib/sales'

export default function PurchaseList() {
  const navigate = useNavigate()
  const { can } = useAuth()
  const [rows, setRows] = useState([])
  const [meta, setMeta] = useState({ last_page: 1, total: 0 })
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [query, setQuery] = useState('')
  const [status, setStatus] = useState('')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    const t = setTimeout(() => { setQuery(search.trim()); setPage(1) }, 300)
    return () => clearTimeout(t)
  }, [search])

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const { data } = await api.get('/purchases', {
        params: { page, q: query || undefined, status: status || undefined },
      })
      setRows(data.data)
      setMeta({ last_page: data.last_page, total: data.total })
    } catch (err) {
      setError(parseError(err).message)
    } finally {
      setLoading(false)
    }
  }, [page, query, status])

  useEffect(() => { load() }, [load])

  return (
    <>
      <header className="page-head page-head-row">
        <div>
          <h1>Achats</h1>
          <p className="muted">{meta.total} {meta.total > 1 ? 'commandes' : 'commande'} fournisseur</p>
        </div>
        {can('purchases.manage') && <Link to="/achats/nouveau" className="btn btn-inline btn-link"><AddIcon className="size-4" aria-hidden="true" /> Nouvelle commande</Link>}
      </header>

      <div className="toolbar">
        <input type="search" placeholder="Rechercher (numéro, fournisseur)…" aria-label="Rechercher"
          value={search} onChange={(e) => setSearch(e.target.value)} />
        <select aria-label="Filtrer par statut" value={status}
          onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
          <option value="">Tous les statuts</option>
          {Object.entries(PURCHASE_STATUS).map(([k, [label]]) => <option key={k} value={k}>{label}</option>)}
        </select>
      </div>

      {error && <div role="alert" className="alert">{error}</div>}

      <div className="table-wrap" aria-busy={loading}>
        <table>
          <thead>
            <tr>
              <th>Numéro</th><th>Fournisseur</th><th>Date</th><th>Livraison prévue</th>
              <th className="num">Total TTC</th><th className="num">Reste à payer</th><th>Statut</th><th>Règlement</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((p) => {
              const pay = paymentState(p)
              return (
                <tr key={p.id} className="row-link" tabIndex={0}
                  onClick={() => navigate(`/achats/${p.id}`)}
                  onKeyDown={(e) => e.key === 'Enter' && navigate(`/achats/${p.id}`)}>
                  <td><Link to={`/achats/${p.id}`} onClick={(e) => e.stopPropagation()}><strong>{p.number}</strong></Link></td>
                  <td>{p.supplier?.name}</td>
                  <td>{fmtDay(p.order_date)}</td>
                  <td>{fmtDay(p.expected_date)}</td>
                  <td className="num">{fmtMoney(p.total)}</td>
                  <td className="num">{pay ? fmtMoney(p.balance) : '—'}</td>
                  <td><PurchasePill po={p} /></td>
                  <td>{pay ? <span className={`pill pill-${pay[1]}`}>{pay[0]}</span> : '—'}</td>
                </tr>
              )
            })}
            {!loading && rows.length === 0 && (
              <tr><td colSpan="8" className="empty">
                {query || status ? 'Aucun résultat pour ces critères.' : 'Aucune commande pour le moment.'}
              </td></tr>
            )}
            {loading && rows.length === 0 && <tr><td colSpan="8" className="empty">Chargement…</td></tr>}
          </tbody>
        </table>
      </div>

      {meta.last_page > 1 && (
        <nav className="pager" aria-label="Pagination">
          <button disabled={page <= 1} onClick={() => setPage(page - 1)}>← Précédent</button>
          <span>Page {page} / {meta.last_page}</span>
          <button disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>Suivant →</button>
        </nav>
      )}
    </>
  )
}
