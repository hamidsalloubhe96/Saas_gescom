import { useEffect, useState } from 'react'
import api, { parseError } from '../api'
import { fmtMoney0, fmtQty } from '../lib/format'
import { fmtDay } from '../lib/sales'

const STATUS = {
  expired: { label: 'Périmé', cls: 'pill-bad' },
  soon: { label: 'Moins de 30 jours', cls: 'pill-warn' },
  watch: { label: 'À surveiller', cls: 'pill-info' },
}

const WINDOWS = [{ v: 30, l: '30 jours' }, { v: 60, l: '60 jours' }, { v: 90, l: '90 jours' }, { v: 180, l: '6 mois' }]

/** Péremptions : lots périmés ou proches de leur date limite, à retirer de la vente ou à écouler en priorité. */
export default function Expiry() {
  const [days, setDays] = useState(90)
  const [q, setQ] = useState('')
  const [data, setData] = useState({ summary: {}, data: [] })
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    const t = setTimeout(() => {
      setLoading(true)
      api.get('/stock/expiry', { params: { days, q: q || undefined } })
        .then(({ data: d }) => { setData(d); setError(null) })
        .catch((err) => setError(parseError(err).message))
        .finally(() => setLoading(false))
    }, 250)
    return () => clearTimeout(t)
  }, [days, q])

  const s = data.summary ?? {}
  const rows = data.data ?? []

  return (
    <>
      <header className="page-head">
        <h1>Péremptions</h1>
        <p className="muted">Lots périmés ou proches de leur date limite. Les lots périmés ne sont jamais vendus à la caisse.</p>
      </header>

      <div className="kpis">
        <div className="kpi"><span className="kpi-label">Lots périmés</span><span className="kpi-value">{s.expired ?? 0}</span></div>
        <div className="kpi"><span className="kpi-label">Valeur périmée (achat)</span><span className="kpi-value">{fmtMoney0(s.expired_value)}</span></div>
        <div className="kpi"><span className="kpi-label">Périment sous 30 jours</span><span className="kpi-value">{s.soon ?? 0}</span></div>
        <div className="kpi"><span className="kpi-label">À surveiller</span><span className="kpi-value">{s.watch ?? 0}</span></div>
      </div>

      <div className="toolbar">
        <input type="search" placeholder="Rechercher un médicament…" aria-label="Rechercher" value={q} onChange={(e) => setQ(e.target.value)} />
        <select aria-label="Période" value={days} onChange={(e) => setDays(Number(e.target.value))}>
          {WINDOWS.map((w) => <option key={w.v} value={w.v}>Périment sous {w.l}</option>)}
        </select>
      </div>

      {error && <div role="alert" className="alert">{error}</div>}

      <div className="table-wrap" aria-busy={loading}>
        <table>
          <thead>
            <tr><th>Produit</th><th>Lot</th><th>Point de vente</th><th>Péremption</th><th className="num">Quantité</th><th className="num">Valeur</th><th>État</th></tr>
          </thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id}>
                <td><strong>{r.product}</strong><div className="muted small">{[r.dosage, r.form].filter(Boolean).join(' · ')}</div></td>
                <td>{r.batch_number}</td>
                <td>{r.branch}</td>
                <td>{fmtDay(r.expires_at)}<div className="muted small">{r.days_left < 0 ? `depuis ${-r.days_left} j` : `dans ${r.days_left} j`}</div></td>
                <td className="num">{fmtQty(r.quantity)}</td>
                <td className="num">{fmtMoney0(r.value)}</td>
                <td><span className={`pill ${STATUS[r.status].cls}`}>{STATUS[r.status].label}</span></td>
              </tr>
            ))}
            {!loading && rows.length === 0 && <tr><td colSpan="7" className="empty">Aucun lot concerné sur cette période.</td></tr>}
            {loading && rows.length === 0 && <tr><td colSpan="7" className="empty">Chargement…</td></tr>}
          </tbody>
        </table>
      </div>
    </>
  )
}
