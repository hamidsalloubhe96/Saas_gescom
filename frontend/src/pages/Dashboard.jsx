import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api, { parseError } from '../api'
import { useAuth } from '../auth'
import SalesChart from '../components/SalesChart'
import { useBusiness } from '../lib/business'
import { fmtMoney0, fmtQty } from '../lib/format'

function Kpi({ label, value, sub, tone, to }) {
  const body = (
    <>
      <span className="kpi-label">{label}</span>
      <strong className={`kpi-value ${tone ? `tone-${tone}` : ''}`}>{value}</strong>
      <span className="kpi-sub">{sub ?? ' '}</span>
    </>
  )
  return to ? <Link to={to} className="kpi">{body}</Link> : <div className="kpi">{body}</div>
}

function Delta({ pct }) {
  if (pct === null) return <>Pas de comparaison possible</>
  const up = pct >= 0
  return <span className={up ? 'delta-up' : 'delta-down'}>{up ? '▲' : '▼'} {up ? '+' : ''}{String(pct).replace('.', ',')} % vs mois dernier</span>
}

export default function Dashboard() {
  const { user, company, can } = useAuth()
  const [data, setData] = useState(null)
  const [error, setError] = useState(null)
  const [days, setDays] = useState(14) // période du graphique : 14 ou 30 jours
  const { hasExpiry } = useBusiness()
  const [expiry, setExpiry] = useState(null)

  useEffect(() => {
    api.get('/dashboard').then(({ data: d }) => setData(d)).catch((err) => setError(parseError(err).message))
  }, [])

  useEffect(() => {
    if (!hasExpiry || !can('products.view')) return
    api.get('/stock/expiry', { params: { days: 30 } }).then(({ data: x }) => setExpiry(x.summary)).catch(() => {})
  }, [hasExpiry, can])

  const today = new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
  const d = data

  return (
    <>
      <header className="page-head page-head-row">
        <div>
          <h1>Bonjour, {user.name}</h1>
          <p className="muted">{company} · {today}</p>
        </div>
        <div className="head-actions wrap">
          {can('pos.use') && <Link to="/caisse" className="btn btn-inline btn-link">Caisse</Link>}
          {can('sales.manage') && <Link to="/ventes/nouveau?type=quote" className="btn btn-secondary btn-inline btn-link">+ Devis</Link>}
          {can('sales.manage') && <Link to="/ventes/nouveau?type=invoice" className="btn btn-secondary btn-inline btn-link">+ Facture</Link>}
          {can('purchases.manage') && <Link to="/achats/nouveau" className="btn btn-secondary btn-inline btn-link">+ Commande</Link>}
        </div>
      </header>

      {error && <div role="alert" className="alert">{error}</div>}
      {!d && !error && <div className="splash" role="status" style={{ minHeight: '40vh' }}>Chargement…</div>}

      {d && (
        <>
          <section className="kpis" aria-label="Indicateurs clés">
            <Kpi label="Ventes du jour" value={fmtMoney0(d.sales.today)} to="/ventes?type=invoice"
              sub={d.register_open ? 'Caisse ouverte' : 'Caisse fermée'} />
            <Kpi label="Ventes du mois" value={fmtMoney0(d.sales.month)} to="/ventes?type=invoice"
              sub={<Delta pct={d.sales.month_change_percent} />} />
            <Kpi label="Panier moyen (mois)" value={fmtMoney0(d.sales.average_basket)}
              sub={`${d.sales.invoices_month} facture${d.sales.invoices_month > 1 ? 's' : ''}`} />
            <Kpi label="À encaisser" value={fmtMoney0(d.receivables.total)} to="/ventes?type=invoice"
              tone={d.receivables.overdue_count > 0 ? 'warn' : undefined}
              sub={d.receivables.overdue_count > 0
                ? `${d.receivables.overdue_count} en retard · ${fmtMoney0(d.receivables.overdue_amount)}`
                : 'Aucun retard'} />
            <Kpi label="À payer (fournisseurs)" value={fmtMoney0(d.payables.total)} to="/achats"
              sub={`${d.payables.count} commande${d.payables.count > 1 ? 's' : ''} non soldée${d.payables.count > 1 ? 's' : ''}`} />
            <Kpi label="Valeur du stock" value={fmtMoney0(d.stock.value)} to="/produits"
              sub={`${d.stock.products} produit${d.stock.products > 1 ? 's' : ''} actif${d.stock.products > 1 ? 's' : ''} · au prix d'achat`} />
            <Kpi label="Stock bas" value={String(d.stock.low_count)} to="/produits?low=1"
              tone={d.stock.low_count > 0 ? 'warn' : undefined}
              sub={d.stock.low_count > 0 ? 'à réapprovisionner' : 'Aucun produit sous le seuil'} />
            {hasExpiry && expiry && (
              <Kpi label="Péremptions" value={String(expiry.expired + expiry.soon)} to="/peremptions"
                tone={expiry.expired + expiry.soon > 0 ? 'warn' : undefined}
                sub={expiry.expired > 0 ? `${expiry.expired} lot${expiry.expired > 1 ? 's' : ''} périmé${expiry.expired > 1 ? 's' : ''}` : expiry.soon > 0 ? 'lots à écouler sous 30 jours' : 'Aucun lot concerné'} />
            )}
            <Kpi label="Devis en cours" value={String(d.pending.open_quotes)} to="/ventes?type=quote"
              sub={d.pending.open_quotes > 0 ? `${fmtMoney0(d.pending.open_quotes_total)} en attente` : 'Aucun devis en attente'} />
          </section>

          {/* À partir de xl : grille de 3 colonnes (graphique + « à traiter », puis meilleures ventes + stock bas). */}
          <div className="grid gap-4 xl:grid-cols-3 [&_.panel]:mb-0">
          <section className="panel xl:order-1 xl:col-span-2">
            <div className="panel-head">
              <h2>Ventes des {days} derniers jours</h2>
              <div className="flex items-center gap-3">
                <span className="muted hidden sm:inline">{fmtMoney0(d.series.slice(-days).reduce((s, x) => s + x.total, 0))} TTC</span>
                <div role="group" aria-label="Période du graphique" className="flex overflow-hidden rounded-lg border border-line text-sm">
                  {[14, 30].map((n) => (
                    <button key={n} type="button" aria-pressed={days === n} onClick={() => setDays(n)}
                      className={days === n ? 'whitespace-nowrap bg-accent px-3 py-1 font-semibold text-on-accent' : 'whitespace-nowrap bg-surface px-3 py-1 text-muted hover:bg-accent-soft'}>{n} j</button>
                  ))}
                </div>
              </div>
            </div>
            <SalesChart series={d.series.slice(-days)} />
          </section>

          <div className="dash-cols xl:contents">
            <section className="panel xl:order-3 xl:col-span-2">
              <div className="panel-head"><h2>Meilleures ventes (30 jours)</h2><span className="muted">CA HT</span></div>
              {d.top_products.length === 0 ? <p className="muted">Pas encore de vente.</p> : (
                <table className="lines view">
                  <thead><tr><th>Produit</th><th className="num">Qté</th><th className="num">CA HT</th></tr></thead>
                  <tbody>
                    {d.top_products.map((p) => (
                      <tr key={`${p.product_id}-${p.name}`}>
                        <td>{p.name}</td><td className="num">{fmtQty(p.quantity)}</td><td className="num">{fmtMoney0(p.revenue)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </section>

            <section className="panel xl:order-4">
              <div className="panel-head">
                <h2>Stock bas</h2>
                {d.stock.low_count > 0 && <Link to="/produits?low=1" className="small">Voir les {d.stock.low_count} →</Link>}
              </div>
              {d.stock.low.length === 0 ? <p className="muted">Aucun produit sous son seuil d'alerte.</p> : (
                <table className="lines view">
                  <thead><tr><th>Produit</th><th className="num">Stock</th><th className="num">Seuil</th></tr></thead>
                  <tbody>
                    {d.stock.low.map((p) => (
                      <tr key={p.id}>
                        <td>{p.name}<div className="muted small">{p.sku}</div></td>
                        <td className="num stock-low">{fmtQty(p.stock_quantity)} {p.unit}</td>
                        <td className="num">{fmtQty(p.min_stock)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </section>
          </div>

          <section className="panel xl:order-2">
            <h2>À traiter</h2>
            <ul className="todo">
              <li>
                <Link to="/ventes?type=quote">Devis en cours</Link>
                <span>{d.pending.open_quotes > 0 ? `${d.pending.open_quotes} · ${fmtMoney0(d.pending.open_quotes_total)}` : 'Aucun'}</span>
              </li>
              <li>
                <Link to="/ventes?type=invoice">Factures en retard de paiement</Link>
                <span className={d.receivables.overdue_count > 0 ? 'stock-low' : ''}>
                  {d.receivables.overdue_count > 0 ? `${d.receivables.overdue_count} · ${fmtMoney0(d.receivables.overdue_amount)}` : 'Aucune'}
                </span>
              </li>
              <li>
                <Link to="/achats">Commandes à réceptionner</Link>
                <span>
                  {d.pending.awaiting_reception > 0
                    ? `${d.pending.awaiting_reception}${d.pending.late_receptions > 0 ? ` dont ${d.pending.late_receptions} en retard` : ''}`
                    : 'Aucune'}
                </span>
              </li>
              <li>
                <Link to="/produits?low=1">Produits sous le seuil d'alerte</Link>
                <span className={d.stock.low_count > 0 ? 'stock-low' : ''}>{d.stock.low_count > 0 ? d.stock.low_count : 'Aucun'}</span>
              </li>
            </ul>
          </section>
          </div>
        </>
      )}
    </>
  )
}
