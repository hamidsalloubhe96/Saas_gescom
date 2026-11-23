import { useCallback, useEffect, useState } from 'react'
import api, { parseError } from '../api'
import { fmtMoney0, fmtQty } from '../lib/format'

const today = () => new Date().toLocaleDateString('en-CA')
const longDate = (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })

/** Boulangerie : production du jour (ingrédients, enregistrement) et bilan de fin de journée (invendus). */
export default function Production() {
  const [tab, setTab] = useState('production')
  const [date, setDate] = useState(today())
  return (
    <>
      <header className="page-head page-head-row">
        <div>
          <h1>Production</h1>
          <p className="muted">Fournées du jour et bilan des invendus.</p>
        </div>
        <input type="date" aria-label="Jour" value={date} max={today()} onChange={(e) => e.target.value && setDate(e.target.value)} className="!w-auto" />
      </header>

      <div className="segmented mb-4" role="tablist" aria-label="Sections">
        {[['production', 'Production du jour'], ['unsold', 'Invendus']].map(([k, l]) => (
          <button key={k} type="button" role="tab" aria-selected={tab === k} className={tab === k ? 'on' : ''} onClick={() => setTab(k)}>{l}</button>
        ))}
      </div>

      {tab === 'production' ? <DayProduction date={date} /> : <Unsold date={date} />}
    </>
  )
}

function DayProduction({ date }) {
  const [plan, setPlan] = useState([])
  const [qty, setQty] = useState({})
  const [needs, setNeeds] = useState(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  const [done, setDone] = useState(null)

  const load = useCallback(() => {
    api.get('/production/plan', { params: { date } }).then(({ data }) => setPlan(data.products)).catch((err) => setError(parseError(err).message))
  }, [date])
  useEffect(() => { setDone(null); setNeeds(null); setQty({}); load() }, [load])

  const lines = plan.map((p) => ({ product_id: p.id, quantity: Number(qty[p.id]) || 0 })).filter((l) => l.quantity > 0)

  // Les besoins se recalculent à chaque saisie (sans rien enregistrer).
  useEffect(() => {
    if (lines.length === 0) { setNeeds(null); return undefined }
    const t = setTimeout(() => {
      api.post('/production/preview', { lines }).then(({ data }) => { setNeeds(data); setError(null) }).catch((err) => setError(parseError(err).message))
    }, 300)
    return () => clearTimeout(t)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [JSON.stringify(lines)])

  async function save() {
    setBusy(true); setError(null)
    try {
      await api.post('/production', { lines, date })
      setDone('Production enregistrée : les ingrédients sont sortis du stock et les produits finis y sont entrés.')
      setQty({}); setNeeds(null); load()
    } catch (err) { setError(parseError(err).message) } finally { setBusy(false) }
  }

  if (plan.length === 0 && !error) return <p className="muted">Aucun produit fabriqué. Sur la fiche d’un produit, choisissez « Fabriqué » et renseignez sa recette.</p>

  return (
    <>
      {error && <div role="alert" className="alert">{error}</div>}
      {done && <div role="status" className="alert alert-ok">{done}</div>}
      <p className="muted small">Production du {longDate(date)}.</p>
      <div className="table-wrap">
        <table>
          <thead><tr><th>Produit</th><th className="num">Déjà produit</th><th className="num">Conseil</th><th className="num" style={{ width: 140 }}>À produire</th><th className="num">Fournées</th></tr></thead>
          <tbody>
            {plan.map((p) => (
              <tr key={p.id}>
                <td><strong>{p.name}</strong><div className="muted small">{fmtQty(p.batch_yield)} {p.unit === 'pièce' && p.batch_yield > 1 ? 'pièces' : p.unit} <span className="whitespace-nowrap">par fournée</span></div></td>
                <td className="num">{fmtQty(p.produced)}</td>
                <td className="num">{p.suggested == null ? '—' : <button type="button" className="link-btn" onClick={() => setQty({ ...qty, [p.id]: String(p.suggested) })}>{p.suggested}</button>}</td>
                <td><input className="num" type="number" min="0" step="any" aria-label={`Quantité à produire : ${p.name}`} value={qty[p.id] ?? ''} onChange={(e) => setQty({ ...qty, [p.id]: e.target.value })} /></td>
                <td className="num">{Number(qty[p.id]) > 0 ? fmtQty(Math.round((Number(qty[p.id]) / p.batch_yield) * 100) / 100) : '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {needs && (
        <section className="mt-4">
          <h2>Ingrédients nécessaires</h2>
          <div className="table-wrap">
            <table>
              <thead><tr><th>Ingrédient</th><th className="num">Besoin</th><th className="num">En stock</th><th>État</th></tr></thead>
              <tbody>
                {needs.ingredients.map((i) => (
                  <tr key={i.ingredient_id}>
                    <td>{i.name}</td><td className="num">{fmtQty(i.needed)} {i.unit}</td><td className="num">{fmtQty(i.in_stock)} {i.unit}</td>
                    <td><span className={`pill ${i.ok ? 'pill-on' : 'pill-bad'}`}>{i.ok ? 'Suffisant' : `Manque ${fmtQty(i.missing)} ${i.unit}`}</span></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}

      <div className="mt-4 flex flex-wrap items-center gap-3">
        <button type="button" className="btn btn-inline" onClick={save} disabled={busy || !needs || !needs.ok}>{busy ? 'Enregistrement…' : 'Enregistrer la production'}</button>
        <span className="muted small">À l’enregistrement, les ingrédients sortent du stock et les produits finis y entrent.</span>
      </div>
    </>
  )
}

function Unsold({ date }) {
  const [data, setData] = useState(null)
  const [discard, setDiscard] = useState({})
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)

  const load = useCallback(() => {
    api.get('/production/unsold', { params: { date } }).then(({ data: d }) => {
      setData(d)
      setDiscard(Object.fromEntries(d.rows.map((r) => [r.product_id, r.validated ? r.discarded > 0 : true])))
    }).catch((err) => setError(parseError(err).message))
  }, [date])
  useEffect(() => { load() }, [load])

  async function validate() {
    setBusy(true); setError(null)
    try {
      const items = data.rows.filter((r) => !r.validated).map((r) => ({ product_id: r.product_id, discard: Boolean(discard[r.product_id]) }))
      const { data: d } = await api.post('/production/unsold', { date, items })
      setData(d)
    } catch (err) { setError(parseError(err).message) } finally { setBusy(false) }
  }

  if (!data) return error ? <div role="alert" className="alert">{error}</div> : <p className="muted">Chargement…</p>
  if (data.rows.length === 0) return <p className="muted">Rien n’a été produit ni vendu ce jour-là.</p>

  const s = data.summary
  const pending = data.rows.some((r) => !r.validated)
  const lossNow = data.rows.reduce((sum, r) => sum + (r.validated ? r.loss : (discard[r.product_id] ? r.unsold * r.unit_cost : 0)), 0)

  return (
    <>
      {error && <div role="alert" className="alert">{error}</div>}
      <div className="kpis">
        <div className="kpi"><span className="kpi-label">Perte du jour (coût)</span><span className={`kpi-value ${lossNow > 0 ? 'tone-warn' : ''}`}>{fmtMoney0(lossNow)}</span></div>
        <div className="kpi"><span className="kpi-label">Taux d’invendus</span><span className="kpi-value">{fmtQty(s.unsold_rate)} %</span></div>
        <div className="kpi"><span className="kpi-label">Invendus</span><span className="kpi-value">{fmtQty(s.unsold)}</span></div>
      </div>

      <div className="table-wrap">
        <table>
          <thead><tr><th>Produit</th><th className="num">Produit</th><th className="num">Vendu</th><th className="num">Invendu</th><th>Que faire ?</th></tr></thead>
          <tbody>
            {data.rows.map((r) => (
              <tr key={r.product_id}>
                <td><strong>{r.name}</strong></td>
                <td className="num">{fmtQty(r.produced)}</td><td className="num">{fmtQty(r.sold)}</td><td className="num">{fmtQty(r.unsold)}</td>
                <td>
                  {r.validated
                    ? <span className="pill pill-off">{r.discarded > 0 ? `Rebut : ${fmtQty(r.discarded)}` : 'Gardé'}</span>
                    : r.unsold > 0
                      ? (
                        <div className="segmented" role="radiogroup" aria-label={`Invendus de ${r.name}`}>
                          <button type="button" role="radio" aria-checked={!discard[r.product_id]} className={!discard[r.product_id] ? 'on' : ''} onClick={() => setDiscard({ ...discard, [r.product_id]: false })}>Garder</button>
                          <button type="button" role="radio" aria-checked={Boolean(discard[r.product_id])} className={discard[r.product_id] ? 'on' : ''} onClick={() => setDiscard({ ...discard, [r.product_id]: true })}>Rebut</button>
                        </div>
                      )
                      : <span className="muted">Rien à décider</span>}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="mt-4 flex flex-wrap items-center gap-3">
        <button type="button" className="btn btn-inline" onClick={validate} disabled={busy || !pending}>{busy ? 'Validation…' : pending ? 'Valider le bilan' : 'Bilan validé'}</button>
        <span className="muted small">Le rebut sort du stock avec le motif « Invendu ». Un bilan validé ne peut plus être modifié.</span>
      </div>
    </>
  )
}
