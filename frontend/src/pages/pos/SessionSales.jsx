import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api, { parseError } from '../../api'
import { useAuth } from '../../auth'
import StatusPill from '../../components/StatusPill'
import { fmtDate, fmtMoney } from '../../lib/format'

export default function SessionSales({ session, onChanged }) {
  const { can } = useAuth()
  const [rows, setRows] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [busyId, setBusyId] = useState(null)

  const load = useCallback(async () => {
    try {
      const { data } = await api.get('/pos/sales')
      setRows(data.data)
    } catch (err) {
      setError(parseError(err).message)
    } finally {
      setLoading(false)
    }
  }, [session.id])

  useEffect(() => { load() }, [load])

  async function voidSale(d) {
    if (!window.confirm(`Annuler la vente ${d.number} ? Les paiements seront retirés et le stock remis.`)) return
    setBusyId(d.id)
    setError(null)
    try {
      await api.post(`/pos/sales/${d.id}/void`)
      await load()
      onChanged()
    } catch (err) {
      setError(parseError(err).message)
    } finally {
      setBusyId(null)
    }
  }

  return (
    <>
      {error && <div role="alert" className="alert">{error}</div>}
      <div className="table-wrap" aria-busy={loading}>
        <table>
          <thead>
            <tr><th>Ticket</th><th>Heure</th><th>Client</th><th className="num">Total</th><th className="num">Reste dû</th><th>Statut</th><th /></tr>
          </thead>
          <tbody>
            {rows.map((d) => (
              <tr key={d.id}>
                <td><strong>{d.number}</strong></td>
                <td>{fmtDate(d.created_at)}</td>
                <td>{d.customer?.name}</td>
                <td className="num">{fmtMoney(d.total)}</td>
                <td className="num">{d.status === 'cancelled' ? '—' : fmtMoney(d.balance)}</td>
                <td><StatusPill doc={d} /></td>
                <td className="actions">
                  <Link to={`/caisse/ticket/${d.id}`} className="link-btn">Ticket</Link>
                  {can('pos.void') && d.status !== 'cancelled' && (
                    <button className="link-btn danger" disabled={busyId === d.id} onClick={() => voidSale(d)}>Annuler</button>
                  )}
                </td>
              </tr>
            ))}
            {!loading && rows.length === 0 && <tr><td colSpan="7" className="empty">Aucune vente dans cette session.</td></tr>}
            {loading && rows.length === 0 && <tr><td colSpan="7" className="empty">Chargement…</td></tr>}
          </tbody>
        </table>
      </div>
    </>
  )
}
