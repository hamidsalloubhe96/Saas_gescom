import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { AddIcon } from '../../components/ui'
import ListCards, { ListCard } from '../../components/ListCards'
import api, { parseError } from '../../api'
import { useAuth } from '../../auth'
import StatusPill from '../../components/StatusPill'
import { fmtMoney } from '../../lib/format'
import { STATUS, TYPE_LABEL, fmtDay } from '../../lib/sales'

export default function SalesList() {
  const [params, setParams] = useSearchParams()
  const type = params.get('type') === 'invoice' ? 'invoice' : 'quote'
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
      const { data } = await api.get('/documents', {
        params: { type, page, q: query || undefined, status: status || undefined },
      })
      setRows(data.data)
      setMeta({ last_page: data.last_page, total: data.total })
    } catch (err) {
      setError(parseError(err).message)
    } finally {
      setLoading(false)
    }
  }, [type, page, query, status])

  useEffect(() => { load() }, [load])

  function switchType(next) {
    setParams({ type: next })
    setPage(1)
    setStatus('')
  }

  const isInvoice = type === 'invoice'

  return (
    <>
      <header className="page-head page-head-row">
        <div>
          <h1>Devis & factures</h1>
          <p className="muted">{meta.total} {meta.total > 1 ? 'documents' : 'document'}</p>
        </div>
        {can('sales.manage') && (
          <Link to={`/ventes/nouveau?type=${type}`} className="btn btn-inline btn-link">
            <AddIcon className="size-4" aria-hidden="true" /> {isInvoice ? 'Nouvelle facture' : 'Nouveau devis'}
          </Link>
        )}
      </header>

      <div className="tabs" role="tablist">
        {['quote', 'invoice'].map((t) => (
          <button key={t} role="tab" aria-selected={type === t} className={type === t ? 'on' : ''}
            onClick={() => switchType(t)}>
            {t === 'quote' ? 'Devis' : 'Factures'}
          </button>
        ))}
      </div>

      <div className="toolbar">
        <input type="search" placeholder="Numéro ou client" aria-label="Rechercher"
          value={search} onChange={(e) => setSearch(e.target.value)} />
        <select aria-label="Filtrer par statut" value={status}
          onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
          <option value="">Tous les statuts</option>
          {Object.entries(STATUS[type]).map(([k, [label]]) => <option key={k} value={k}>{label}</option>)}
        </select>
      </div>

      {error && <div role="alert" className="alert">{error}</div>}

      <ListCards loading={loading} isEmpty={rows.length === 0}
        empty={query || status ? 'Aucun résultat pour ces critères.' : `Aucun ${TYPE_LABEL[type].toLowerCase()} pour le moment.`}>
        {rows.map((d) => (
          <ListCard key={d.id} to={`/ventes/${d.id}`} title={<span className="whitespace-nowrap">{d.number ?? 'Sans numéro'}</span>} badge={<StatusPill doc={d} />} sub={d.customer?.name}
            details={[
              [isInvoice ? 'Échéance' : 'Validité', fmtDay(d.due_date)],
              ['Total TTC', fmtMoney(d.total)],
              ...(isInvoice && !['draft', 'cancelled'].includes(d.status) ? [['Reste dû', fmtMoney(d.balance), Number(d.balance) > 0 ? 'bad' : undefined]] : []),
            ]} />
        ))}
      </ListCards>

      <div className="table-wrap max-lg:hidden" aria-busy={loading}>
        <table>
          <thead>
            <tr>
              <th>Numéro</th><th>Client</th><th>Date</th><th>{isInvoice ? 'Échéance' : 'Validité'}</th>
              <th className="num">Total TTC</th>{isInvoice && <th className="num">Reste dû</th>}<th>Statut</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((d) => (
              <tr key={d.id} className="row-link" tabIndex={0}
                onClick={() => navigate(`/ventes/${d.id}`)}
                onKeyDown={(e) => e.key === 'Enter' && navigate(`/ventes/${d.id}`)}>
                <td><Link to={`/ventes/${d.id}`} onClick={(e) => e.stopPropagation()}>
                  <strong>{d.number ?? 'Brouillon'}</strong></Link></td>
                <td>{d.customer?.name}</td>
                <td>{fmtDay(d.issue_date)}</td>
                <td>{fmtDay(d.due_date)}</td>
                <td className="num">{fmtMoney(d.total)}</td>
                {isInvoice && <td className="num">{['draft', 'cancelled'].includes(d.status) ? '—' : fmtMoney(d.balance)}</td>}
                <td><StatusPill doc={d} /></td>
              </tr>
            ))}
            {!loading && rows.length === 0 && (
              <tr><td colSpan={isInvoice ? 7 : 6} className="empty">
                {query || status ? 'Aucun résultat pour ces critères.' : `Aucun ${TYPE_LABEL[type].toLowerCase()} pour le moment.`}
              </td></tr>
            )}
            {loading && rows.length === 0 && <tr><td colSpan={isInvoice ? 7 : 6} className="empty">Chargement…</td></tr>}
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
